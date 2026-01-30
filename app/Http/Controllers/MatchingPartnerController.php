<?php

namespace App\Http\Controllers;

use App\Http\Requests\MatchingPartnerIndexRequest;
use App\Services\MatchingPartnerService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use JsonException;

class MatchingPartnerController extends Controller
{
    public function __construct(
        private readonly ResponseService $responseService
    ) {
    }

    public function index(MatchingPartnerIndexRequest $request, MatchingPartnerService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user || !isset($user->id)) {
            return $this->responseService->error(
                message: 'Unauthorized.',
                code: 'UNAUTHORIZED',
                data: null,
                status: 401
            );
        }

        $validated = $request->validated();
        $userId = (int) $user->user_id;
        try {
            $result = $service->getPartners([
                'user_id' => $userId,
                'data_source_id' => $validated['live_chat_data_source_id']??config('eventos.live_chat_data_source_id'),
                'event_id' => config('eventos.event'),
                'language_id' => (int) ($validated['language_id'] ?? 1),
                'limit_exhibitors' => (int) ($validated['limit_exhibitors'] ?? 10),
                'limit_visitors' => (int) ($validated['limit_visitors'] ?? 10),
                'limit_networking_per_name' => (int) ($validated['limit_networking_per_name'] ?? 10),
                'keyword' => $validated['keyword']??null,
            ]);
        } catch (JsonException $e) {
            return $this->responseService->error(
                message: 'Failed to build partners response.',
                code: 'JSON_EXCEPTION',
                data: [
                    'error' => $e->getMessage(),
                ],
                status: 500
            );
        }

        return $this->responseService->success(
            data: $result,
            code: 'OK',
            message: ''
        );
    }
}
