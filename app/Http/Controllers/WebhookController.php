<?php

namespace App\Http\Controllers;

use App\Repositories\UsersRepository;
use App\Services\BusinessApproveWebhookService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{


    public function __construct(
        private readonly ResponseService               $responseService,
        private readonly BusinessApproveWebhookService $businessApproveWebhookService,
        private readonly UsersRepository               $usersRepository,
    )
    {

    }

    public function handleCsvListTriggerWebhook(Request $request): JsonResponse
    {
        // Accept either:
        // 1) Body is an array of items: [ {name, fileurl}, ... ]
        // 2) Body wrapped: { "data": [ ... ] } or { "items": [ ... ] }
        $items = $request->all();

        $validator = Validator::make(['items' => $items], [
            'items' => ['required', 'array'],
            'items.*.name' => ['required', 'string', 'max:191'],
            'items.*.fileurl' => ['required', 'string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return $this->responseService->error(
                'Invalid payload',
                422,
                $validator->errors()->toArray(),
                422
            );
        }

        $collection = collect($validator->validated()['items'])
            ->map(static function (array $item): array {
                return [
                    'name' => trim((string)$item['name']),
                    'fileurl' => ltrim(trim((string)$item['fileurl']), '/'),
                ];
            });
        $deduped = $collection->reverse()->unique('name')->reverse()->values();
        $skippedDuplicates = $collection->count() - $deduped->count();

        $queued = 0;
        $failed = [];

        foreach ($deduped as $item) {
            $commandName = $item['name'];
            $fileUrl = $item['fileurl'];

            // Basic guard to avoid command injection / weird names
            if (!preg_match('/^[a-zA-Z0-9:_-]+$/', $commandName)) {
                $failed[] = [
                    'name' => $commandName,
                    'fileurl' => $fileUrl,
                    'error' => 'Invalid command name format',
                ];
                Log::warning('csv-webhook: invalid command name', ['name' => $commandName]);
                continue;
            }

            try {
                // Safer than string concatenation
                Process::path(base_path())
                    ->quietly()
                    ->start(['php', 'artisan', $commandName, $fileUrl]);

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

        return $this->responseService->success([
            'received' => $collection->count(),
            'queued' => $queued,
            'skipped_duplicates' => $skippedDuplicates,
            'failed' => $failed,
        ],
            200,

            'Webhook processed successfully',
            status: 202
        );
    }


    public function businessApprovement(Request $request): JsonResponse
    {
        $data = $request->all();
        Log::channel('webhook')->info($data);

        $this->businessApproveWebhookService->process($data);
        return $this->responseService->success('Webhook processed successfully', 200, status: 201);
    }

    public function userRegistration(Request $request): JsonResponse
    {
        $data = $request->all();
        Log::channel('webhook')->info($data);
        if ($data["module_code"] === "Register") {
            $user = $this->usersRepository->findByUuid($data['user']['user_uuid']);
            if ($user) {
                $user->user_id = $data['user']['user_id'];
                $user->save();
            } else {
                $this->usersRepository->create([
                    'uuid' => $data['user']['user_uuid'],
                    'user_id' => $data['user']['user_id'],
                    'is_first_login' => true,
                ]);
            }
        }
        return $this->responseService->success('Register user', 200, status: 201);
    }
}
