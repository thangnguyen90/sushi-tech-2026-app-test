<?php

namespace App\Repositories;

use App\Models\MatchingUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Enums\MatchingStatus;
class MatchingUserRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return MatchingUser::class;
    }

    /**
     * Fetch all "deal done" rows for the current user (no pagination).
     * Optionally filter by event id when provided.
     *
     * @return Collection<int, object> Each item contains: peer_user_id, updated_at
     */
    public function getDealDoneListAllForOwner(
        int $ownerUserId,
        int $status,
        ?int $eventId = null
    ): Collection {
        $query = $this->query()
            ->select(['peer_user_id', 'updated_at'])
            ->where('owner_user_id', $ownerUserId)
            ->where('status', $status);

        if ($eventId !== null) {
            $query->where('event_id', $eventId);
        }

        return $query
            ->orderByDesc('peer_user_id')
            ->get();
    }

    /**
     * Mark both sides as "deal done" (status=4) only if the matching exists in both directions.
     * Optionally scope by event_id when provided.
     *
     * Returns true if update was applied, false if mutual match condition is not met.
     */
    public function markDealDoneIfMutual(
        int $ownerUserId,
        array $peerUserId,
        int $dealDoneStatus,
        ?int $eventId = null
    ): bool {
        return DB::transaction(function () use ($ownerUserId, $peerUserId, $dealDoneStatus, $eventId) {
            $aToB = $this->query()
                ->where('owner_user_id', $ownerUserId)
                ->whereIn('peer_user_id', $peerUserId);

            $bToA = $this->query()
                ->whereIn('owner_user_id', $peerUserId)
                ->where('peer_user_id', $ownerUserId);

            if ($eventId !== null) {
                $aToB->where('event_id', $eventId);
                $bToA->where('event_id', $eventId);
            }

            // Both directions must exist
            if (!$aToB->exists() || !$bToA->exists()) {
                return false;
            }

            // Update both directions to deal done
            $aToB->update(['status' => $dealDoneStatus]);
            $bToA->update(['status' => $dealDoneStatus]);

            return true;
        });
    }

    public function addOrUpdateDataFromWebhook(array $data): void
    {
        $matchingUser = $this->query()
            ->where('owner_user_id', $data['applicant']['user']['user_id'])
            ->where('owner_uuid', $data['applicant']['user']['user_uuid'])
            ->where('peer_user_id', $data['recipient']['exhibitor_administrator_id'])
            ->where('peer_uuid', $data['recipient']['exhibitor_administrator_uuid'])
            ->where('event_id', $data['basic_information']['event_id'] ?? 0)
            ->first();

        if (!$matchingUser)  {
            $this->query()->create([
                'owner_user_id' => $data['applicant']['user']['user_id'],
                'owner_uuid' => $data['applicant']['user']['user_uuid'],
                'peer_user_id' => $data['recipient']['exhibitor_administrator_id'],
                'peer_uuid' => $data['recipient']['exhibitor_administrator_uuid'],
                'event_id' => $data['basic_information']['event_id'] ?? 0,
                'status' => MatchingStatus::DEAL_DONE->value,
            ]);
        }

        $matchingUser = $this->query()
            ->where('owner_user_id', $data['recipient']['exhibitor_administrator_id'])
            ->where('owner_uuid', $data['recipient']['exhibitor_administrator_uuid'])
            ->where('peer_user_id', $data['applicant']['user']['user_id'])
            ->where('peer_uuid', $data['applicant']['user']['user_uuid'])
            ->where('event_id', $data['basic_information']['event_id'] ?? 0)
            ->first();
        if(!$matchingUser)  {
            $this->query()->create([
                'owner_user_id' => $data['recipient']['exhibitor_administrator_id'],
                'owner_uuid' => $data['recipient']['exhibitor_administrator_uuid'],
                'peer_user_id' => $data['applicant']['user']['user_id'],
                'event_id' => $data['basic_information']['event_id'] ?? 0,
                'peer_uuid' => $data['applicant']['user']['user_uuid'],
                'status' => MatchingStatus::DEAL_DONE->value,
            ]);
        }
    }
}
