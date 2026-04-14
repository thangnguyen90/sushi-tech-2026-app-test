<?php

namespace App\Http\Controllers;

use App\Repositories\UsersRepository;
use App\Services\BusinessApproveWebhookService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WebhookController extends Controller
{
    public function __construct(
        private readonly ResponseService $responseService,
        private readonly BusinessApproveWebhookService $businessApproveWebhookService,
        private readonly UsersRepository $usersRepository,
    ) {}

    public function handleCsvListTriggerWebhook(Request $request): JsonResponse
    {
        // Accept either:
        // 1) Body is an array of items: [ {name, fileurl}, ... ]
        // 2) Body wrapped: { "data": [ ... ] } or { "items": [ ... ] }
        $items = $request->all();
        Log::channel('webhook')->info(json_encode($items));
        $collection = collect($items)
            ->map(static function (array $item): array {
                $name = trim((string) ($item['name'] ?? ''));
                $fileurlRaw = (string) ($item['fileurl'] ?? '');

                // remove accidental newlines/spaces + normalize leading slash
                $fileurl = ltrim(preg_replace('/\s+/', '', trim($fileurlRaw)), '/');

                return [
                    'name' => $name,
                    'fileurl' => $fileurl,
                    'ts' => self::extractCsvTimestamp($fileurl), // int|null
                ];
            })
            ->filter(static fn (array $i) => $i['name'] !== '' && $i['fileurl'] !== '');

        // pick newest per name by ts (fallback to fileurl string if ts missing)
        $deduped = $collection
            ->groupBy('name')
            ->map(static function ($group) {
                return $group
                    ->sortByDesc(static fn (array $i) => $i['ts'] ?? -1)
                    ->sortByDesc(static fn (array $i) => $i['fileurl']) // tie-breaker
                    ->first();
            })
            ->values()
            ->map(static fn (array $i) => [
                'name' => $i['name'],
                'fileurl' => $i['fileurl'],
            ]);
        $skippedDuplicates = $collection->count() - $deduped->count();
        $queued = 0;
        $failed = [];

        return tap($this->responseService->success([
            'received' => $collection->count(),
            'queued' => $queued,
            'skipped_duplicates' => $skippedDuplicates,
            'failed' => $failed,
        ],
            200,

            'Webhook processed successfully',
            status: 201
        ), static function () use ($deduped, &$queued, &$failed) {
            foreach ($deduped as $item) {
                $commandName = $item['name'];
                $fileUrl = $item['fileurl'];
                try {
                    // Safer than string concatenation
                    $command = 'php artisan '.$item['name'].' /'.$item['fileurl'];
                    //                    dd($command);
                    Process::path(base_path())->quietly()->start($command);
                    $queued++;
                } catch (\Throwable $e) {
                    $failed[] = [
                        'name' => $commandName,
                        'fileurl' => $fileUrl,
                        'error' => $e->getMessage(),
                    ];

                    Log::error('csv-webhook: failed to start process', [
                        'name' => $commandName,
                        'fileurl' => $fileUrl,
                        'exception' => $e,
                    ]);
                }
            }
        });

    }

    public function businessAppointment(Request $request): JsonResponse
    {
        return $this->handleBusinessAppointmentWebhook($request, 'BusinessAppointment');
    }

    public function businessAppointmentApproved(Request $request): JsonResponse
    {
        return $this->handleBusinessAppointmentWebhook($request, 'BusinessAppointmentApproved');
    }

    public function businessAppointmentRejected(Request $request): JsonResponse
    {
        return $this->handleBusinessAppointmentWebhook($request, 'BusinessAppointmentReject');
    }

    public function businessAppointmentCancelled(Request $request): JsonResponse
    {
        return $this->handleBusinessAppointmentWebhook($request, 'BusinessAppointmentCancel');
    }

    public function businessAppointmentRescheduled(Request $request): JsonResponse
    {
        return $this->handleBusinessAppointmentWebhook($request, 'BusinessAppointmentReschedule');
    }

    public function userRegistration(Request $request): JsonResponse
    {
        $data = $request->all();
        if ($data['module_code'] === 'Register') {
            $user = $this->usersRepository->findByUuid($data['user']['user_uuid']);
            if ($user) {
                $user->user_id = $data['user']['user_id'];
                $user->webhook_data = $data['user'];
                $user->save();
            } else {
                $this->usersRepository->create([
                    'uuid' => $data['user']['user_uuid'],
                    'user_id' => $data['user']['user_id'],
                    'is_first_login' => true,
                    'webhook_data' => $data['user'],
                ]);

            }
        }

        return $this->responseService->success('Register user', 200, status: 201);
    }

    private static function extractCsvTimestamp(string $fileurl): ?int
    {
        // csv/2026-02-04-11-17-06_xxx.csv.gz
        if (preg_match('~\bcsv/(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})_~', $fileurl, $m) !== 1) {
            return null;
        }

        $dt = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
        $ts = strtotime($dt);

        return $ts === false ? null : $ts;
    }

    private function handleBusinessAppointmentWebhook(
        Request $request,
        ?string $moduleCode = null
    ): JsonResponse {
        $data = $request->all();

        if ($moduleCode !== null) {
            $data['module_code'] = $moduleCode;
        }

        $this->businessApproveWebhookService->process($data);

        return $this->responseService->success('Webhook processed successfully', 200, status: 201);
    }
}
