<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkMatchingRequest;
use App\Repositories\MatchingUserRepository;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MatchingUserController extends Controller
{
    private const STATUS_DEAL_DONE = 4;

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

        $ownerUserId = (int) $user->id;

        $rows = $this->matchingUserRepository->getDealDoneListAllForOwner(
            ownerUserId: $ownerUserId,
            status: self::STATUS_DEAL_DONE
        );

        $peerUserIds = $rows
            ->map(fn ($r) => (int) $r->peer_user_id)
            ->values()
            ->all();

        return $this->responseService->success(
            data: [
                'owner_user_id' => $ownerUserId,
                'status' => self::STATUS_DEAL_DONE,
                'peer_user_ids' => $peerUserIds,
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
    public function markDealDone(Request $request, MarkMatchingRequest $matchingRequest): JsonResponse
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

        $ownerUserId = (int) $user->id;

        $peerUserId = $matchingRequest['peer_user_id'];
        $ok = $this->matchingUserRepository->markDealDoneIfMutual(
            ownerUserId: $ownerUserId,
            peerUserId: $peerUserId,
            dealDoneStatus: self::STATUS_DEAL_DONE
        );

        if (!$ok) {
            return $this->responseService->error(
                message: 'Both users must be matched before setting deal done.',
                code: 'NOT_MUTUAL_MATCH',
                data: [
                    'owner_user_id' => $ownerUserId,
                    'status' => self::STATUS_DEAL_DONE,
                    'peer_user_ids' => [],
                ],
                status: 409
            );
        }

        return $this->responseService->success(
            data: [
                'owner_user_id' => $ownerUserId,
                'status' => self::STATUS_DEAL_DONE,
                'peer_user_ids' => [$peerUserId],
            ],
            code: 'OK',
            message: ''
        );
    }
}
