<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkMatchingRequest;
use App\Repositories\MatchingUserRepository;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MatchingUserController extends Controller
{

    public function __construct(
        private readonly MatchingUserRepository $matchingUserRepository,
        private readonly ResponseService $responseService
    ) {}

    /**
     * GET /api/v1/matching/negotiations
     * Optional: ?event_id=123
     */
    public function negotiationsList(Request $request): JsonResponse
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

        $ownerUserId = (int) $user->user_id;

        $rows = $this->matchingUserRepository->getDealDoneListAllForOwner(
            ownerUserId: $ownerUserId,
            status: config('constants.STATUS.DEAL_DONE')
        );

        return $this->responseService->success(
            data: [
                'owner_user_id' => $ownerUserId,
                'status' => config('constants.STATUS.DEAL_DONE'),
                'peer_user_ids' => $rows->pluck('peer_uuid')->toArray(),
            ],
            code: 'OK',
            message: ''
        );
    }

    /**
     * POST /api/v1/matching/negotiations
     * Body (snake_case): { "peer_user_id": 123, "event_id": 456? }
     *
     * Set status=4 only if both directions already exist in matching_users.
     */
    public function markDealDone(MarkMatchingRequest $matchingRequest): JsonResponse
    {
        $user = $matchingRequest->user();
        if (!$user || !isset($user->id)) {
            return $this->responseService->error(
                message: 'Unauthorized.',
                code: 'UNAUTHORIZED',
                data: null,
                status: 401
            );
        }
        $status = (int) $matchingRequest->status;
        $ownerUserId = (int) $user->profile_id;


        $peerUserId = $matchingRequest['peer_user_id'];
        $ok = $this->matchingUserRepository->markDealDoneIfMutual(
            ownerUserId: $ownerUserId,
            peerUserId: $peerUserId,
            status: $status
        );

        if (!$ok) {
            return $this->responseService->error(
                message: 'Both users must be matched before setting deal done.',
                code: 'NOT_MUTUAL_MATCH',
                data: [
                    'owner_user_id' => $ownerUserId,
                    'status' => $status,
                    'peer_user_ids' => [],
                ],
                status: 409
            );
        }

        return $this->responseService->success(
            data: [
                'owner_user_id' => $user->user_id ?? $user->exhibitor_administrator_id,
                'peer_user_ids' => [$peerUserId],
            ],
            code: 'OK',
            message: ''
        );
    }
}
