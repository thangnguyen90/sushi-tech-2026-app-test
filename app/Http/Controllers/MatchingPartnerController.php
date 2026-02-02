<?php

namespace App\Http\Controllers;

use App\Http\Requests\MatchingPartnerIndexRequest;
use App\Services\MatchingPartnerService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use App\Services\LiveChatProfileDetailsService;
use Illuminate\Http\JsonResponse;
use JsonException;

class MatchingPartnerController extends Controller
{
    public function __construct(
        private readonly ResponseService $responseService,
        private LiveChatProfileDetailsService $liveChatProfileDetailsService
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
        $userId = (int) $user->user_id ;
        try {
            $result = $service->getPartners([
                'user_id' => $userId,
                'exhibitor_administrator_id' =>  $user->exhibitor_administrator_id,
                'data_source_id' => $validated['live_chat_data_source_id']??config('eventos.live_chat_data_source_id'),
                'event_id' => config('eventos.event'),
                'language_id' => (int) ($validated['language_id'] ?? 1),
                'limit_exhibitors' => (int) ($validated['limit_exhibitors'] ?? config('constants.LIMIT_EXHIBITORS')),
                'limit_visitors' => (int) ($validated['limit_visitors'] ?? config('constants.LIMIT_VISITORS')),
                'limit_networking_per_name' => (int) ($validated['limit_networking_per_name'] ?? config('constants.LIMIT_NETWORKING_PER_NAME')),
                'keyword' => $validated['keyword']??null,
                'option_values' => $validated['option_values'] ?? [],
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

    public function show(Request $request, int $profile_id): JsonResponse
    {
        $lang = (string) $request->header('language', 'jpn');

        $ctx = [
            'profile_id' => $profile_id,
            'lang' => $lang,
        ];

        $result = $this->liveChatProfileDetailsService->getDetail($ctx);

        return $this->responseService->success(
            data: $result,
            code: 'OK',
            message: ''
        );
    }
}
