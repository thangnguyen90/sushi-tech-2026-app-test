<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Eventos\User\UserService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
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
    protected $signature = 'app:update-user-id-in-table-users';

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
        $users = User::query()
            ->whereNull('user_id')
            ->whereNotNull('uuid')
            ->orderBy('id')
            ->get(['id', 'uuid', 'user_id']);

        if ($users->isEmpty()) {
            $this->info('No users found with null user_id.');

            return self::SUCCESS;
        }

        try {
            $userList = $this->extractUserList($this->userService->getUsersList(0));
        } catch (GuzzleException|Throwable $e) {
            $this->error('Failed to fetch user list from Eventos: ' . $e->getMessage());

            return self::FAILURE;
        }
        $userIdByQrCode = $this->buildUserIdByQrCodeMap($userList);

        $updatedCount = 0;
        $skippedMissingQrCodeCount = 0;
        $skippedMissingMatchCount = 0;
        $failedCount = 0;

        foreach ($users as $user) {
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
                    $this->warn(sprintf('Skipped user [%s]: user_qrcode not found.', $user->uuid));

                    continue;
                }

                $matchedUserId = $userIdByQrCode[$userQrCode] ?? null;
                if ($matchedUserId === null) {
                    $skippedMissingMatchCount++;
                    $this->warn(sprintf('Skipped user [%s]: no matching user_id for QR code [%s].', $user->uuid, $userQrCode));

                    continue;
                }

                $user->forceFill([
                    'user_id' => $matchedUserId,
                ])->save();

                $updatedCount++;
            } catch (Throwable $throwable) {
                $failedCount++;
                $this->error(sprintf('Failed user [%s]: %s', $user->uuid, $throwable->getMessage()));
            }
        }

        $this->info(sprintf(
            'Processed %d users. Updated=%d SkippedMissingQr=%d SkippedMissingMatch=%d Failed=%d',
            $users->count(),
            $updatedCount,
            $skippedMissingQrCodeCount,
            $skippedMissingMatchCount,
            $failedCount
        ));

        return self::SUCCESS;
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

    /**
     * @param  array<int, array<string, mixed>>  $userList
     * @return array<string, int>
     */
    private function buildUserIdByQrCodeMap(array $userList): array
    {
        $userIdByQrCode = [];
        $ambiguousQrCodes = [];

        foreach ($userList as $user) {
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

        return $userIdByQrCode;
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
