<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Eventos\User\UserService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

class UpdateUserIdInTableUsers extends Command
{
    public function __construct(
        private readonly UserService $userService,
    ) {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-user-id-in-table-users
        {--chunk=200 : Number of local users to hydrate per chunk while iterating}
        {--per-page=100 : Number of Eventos users to fetch per API page}
        {--concurrency=10 : Number of concurrent Eventos page requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update missing users.user_id values by matching Eventos user QR codes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $missingUserCount = User::query()
            ->whereNull('user_id')
            ->whereNotNull('uuid')
            ->count();

        if ($missingUserCount === 0) {
            $this->info('No users found with null user_id.');

            return self::SUCCESS;
        }

        try {
            $userIdByQrCode = $this->fetchUserIdByQrCodeMapWithProgress();
        } catch (GuzzleException|Throwable $e) {
            $this->error('Failed to fetch user list from Eventos: '.$e->getMessage());

            return self::FAILURE;
        }

        $updatedCount = 0;
        $skippedMissingQrCodeCount = 0;
        $skippedMissingMatchCount = 0;
        $failedCount = 0;
        $skipSamples = [];
        $errorSamples = [];
        $chunkSize = max((int) $this->option('chunk'), 1);
        $updateProgressBar = $this->output->createProgressBar($missingUserCount);
        $updateProgressBar->setFormat('Updating users %current%/%max% [%bar%] %percent:3s%%');
        $updateProgressBar->start();

        foreach ($this->missingUsersQuery()->lazyById($chunkSize, 'id') as $user) {
            try {
                $userDetail = $this->userService->getUsersByUuid($user->uuid, 0);
                $userQrCode = $this->extractString($userDetail, [
                    'user_qrcode',
                    'data.user_qrcode',
                    'user.user_qrcode',
                    'data.user.user_qrcode',
                ]);

                if ($userQrCode === null) {
                    $skippedMissingQrCodeCount++;
                    $this->rememberSample(
                        $skipSamples,
                        sprintf('Skipped user [%s]: user_qrcode not found.', $user->uuid)
                    );

                    continue;
                }

                $matchedUserId = $userIdByQrCode[$userQrCode] ?? null;
                if ($matchedUserId === null) {
                    $skippedMissingMatchCount++;
                    $this->rememberSample(
                        $skipSamples,
                        sprintf('Skipped user [%s]: no matching user_id for QR code [%s].', $user->uuid, $userQrCode)
                    );

                    continue;
                }

                DB::transaction(function () use ($user, $matchedUserId, &$updatedCount): void {
                    $lockedUser = User::query()
                        ->select(['id', 'user_id', 'uuid'])
                        ->lockForUpdate()
                        ->find($user->id);

                    if ($lockedUser === null || $lockedUser->user_id !== null) {
                        return;
                    }

                    $lockedUser->forceFill([
                        'user_id' => $matchedUserId,
                    ])->save();

                    $updatedCount++;
                }, 3);
            } catch (Throwable $throwable) {
                $failedCount++;
                $this->rememberSample(
                    $errorSamples,
                    sprintf('Failed user [%s]: %s', $user->uuid, $throwable->getMessage())
                );
            } finally {
                $updateProgressBar->advance();
            }
        }

        $updateProgressBar->finish();
        $this->newLine(2);
        $this->writeSamples('Sample skips', $skipSamples, 'warn');
        $this->writeSamples('Sample errors', $errorSamples, 'error');
        $this->info(sprintf(
            'Processed %d users. Updated=%d SkippedMissingQr=%d SkippedMissingMatch=%d Failed=%d',
            $missingUserCount,
            $updatedCount,
            $skippedMissingQrCodeCount,
            $skippedMissingMatchCount,
            $failedCount
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function fetchUserIdByQrCodeMapWithProgress(): array
    {
        $this->line('Fetching Eventos user list...');

        $userIdByQrCode = [];
        $ambiguousQrCodes = [];
        $progressBar = null;

        $perPage = max((int) $this->option('per-page'), 100);
        $concurrency = max((int) $this->option('concurrency'), 10);

        $this->userService->forEachUserListPageParallel(function (array $payload, int $page) use (&$userIdByQrCode, &$ambiguousQrCodes, &$progressBar): void {
            $users = $this->extractUserList($payload);

            if ($progressBar === null) {
                $totalUsers = $this->extractInt($payload, [
                    'total',
                    'meta.total',
                    'pagination.total',
                ]) ?? max(count($users), 1);

                $progressBar = $this->createUserListProgressBar($totalUsers);
                $progressBar->start();
            }

            foreach ($users as $user) {
                $userQrCode = $this->extractString($user, [
                    'user_qrcode',
                    'user.user_qrcode',
                ]);
                $userId = $this->extractInt($user, [
                    'user_id',
                    'user.user_id',
                ]);

                if ($userQrCode === null || $userId === null) {
                    continue;
                }

                if (isset($ambiguousQrCodes[$userQrCode])) {
                    continue;
                }

                if (isset($userIdByQrCode[$userQrCode]) && $userIdByQrCode[$userQrCode] !== $userId) {
                    unset($userIdByQrCode[$userQrCode]);
                    $ambiguousQrCodes[$userQrCode] = true;

                    continue;
                }

                $userIdByQrCode[$userQrCode] = $userId;
            }

            $progressBar->advance(count($users));
        }, $concurrency, $perPage);

        if ($progressBar instanceof ProgressBar) {
            $progressBar->finish();
            $this->newLine();
        }

        $this->info(sprintf(
            'Built QR map with %d unique entries.',
            count($userIdByQrCode)
        ));

        return $userIdByQrCode;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function missingUsersQuery(): Builder
    {
        return User::query()
            ->whereNull('user_id')
            ->whereNotNull('uuid')
            ->orderBy('id')
            ->select(['id', 'uuid']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractUserList(array $payload): array
    {
        $candidates = [
            data_get($payload, 'data'),
            data_get($payload, 'users'),
            data_get($payload, 'list'),
            data_get($payload, 'result'),
            $payload,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || ! array_is_list($candidate)) {
                continue;
            }

            $rows = array_values(array_filter($candidate, static fn (mixed $item): bool => is_array($item)));

            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    private function createUserListProgressBar(int $totalUsers): ProgressBar
    {
        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->setFormat('Fetching Eventos users %current%/%max% [%bar%] %percent:3s%%');

        return $progressBar;
    }

    /**
     * @param  array<int, string>  $samples
     */
    private function rememberSample(array &$samples, string $message): void
    {
        if (count($samples) >= 5) {
            return;
        }

        $samples[] = $message;
    }

    /**
     * @param  array<int, string>  $samples
     */
    private function writeSamples(string $title, array $samples, string $method): void
    {
        if ($samples === []) {
            return;
        }

        $this->{$method}($title.':');

        foreach ($samples as $sample) {
            $this->line(' - '.$sample);
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function extractString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (! is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '') {
                continue;
            }

            return $normalized;
        }

        return null;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function extractInt(array $payload, array $paths): ?int
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if ($value === null) {
                continue;
            }

            if (is_int($value)) {
                return $value;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized === '' || preg_match('/^-?\d+$/', $normalized) !== 1) {
                continue;
            }

            return (int) $normalized;
        }

        return null;
    }
}
