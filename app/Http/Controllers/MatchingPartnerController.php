<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiRecommendRequest;
use App\Http\Requests\MatchingPartnerIndexRequest;
use App\Services\AiRecommendService;
use App\Services\MatchingPartnerService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use App\Services\LiveChatProfileDetailsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
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
        $user = $request?->user() ;
//        if (!$user || !isset($user->id)) {
//            return $this->responseService->error(
//                message: 'Unauthorized.',
//                code: 'UNAUTHORIZED',
//                data: null,
//                status: 401
//            );
//        }

        $validated = $request->validated();
        $userId =  isset($user->user_id) ? (int) $user?->user_id : null;
        $partnerType = $validated['type'] ?? null;
        $seed = $validated['seed'] ?? Str::uuid()->toString();
        $defaultPerPage = $partnerType === 'exhibitor'
            ? (int) ($validated['limit_exhibitors'] ?? config('constants.LIMIT_EXHIBITORS'))
            : (int) ($validated['limit_visitors'] ?? config('constants.LIMIT_VISITORS'));

        try {
            $result = $service->getPartners([
                'profile_id' => $user->profile_id ?? null,
                'user_id' => $userId,
                'data_source_id' => $validated['live_chat_data_source_id']??config('eventos.live_chat_data_source_id'),
                'event_id' => config('eventos.event'),
                'language_id' => (int) ($validated['language_id'] ?? 1),
                'limit_exhibitors' => (int) ($validated['limit_exhibitors'] ?? config('constants.LIMIT_EXHIBITORS')),
                'limit_visitors' => (int) ($validated['limit_visitors'] ?? config('constants.LIMIT_VISITORS')),
                'limit_networking_per_name' => (int) ($validated['limit_networking_per_name'] ?? config('constants.LIMIT_NETWORKING_PER_NAME')),
                'type' => $partnerType,
                'seed' => $seed,
                'page' => (int) ($validated['page'] ?? 1),
                'per_page' => (int) ($validated['per_page'] ?? $defaultPerPage),
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

    public function aiRecommend(AiRecommendRequest $request, AiRecommendService $service): JsonResponse
    {
        $validated = $request->validated();
        $currentUserUuid = (string) ($request->user()?->uuid ?? $request->header('user-uuid', ''));

        $result = $service->buildRecommendResult(
            userUuid: $currentUserUuid,
            content: $validated['content'],
            overrideUserUuidList: $validated['user_uuid_list'] ?? []
        );

        return $this->responseService->success(
            data: $result,
            code: 'OK',
            message: ''
        );
    }
}
