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
            ->select(['peer_uuid', 'updated_at'])
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
        // Extract applicant information based on type
        $applicantUserId = $data['applicant']['user_id']
            ?? $data['applicant']['user']['user_id']
            ?? $data['applicant']['exhibitor_administrator_id']
            ?? null;
        $applicantUuid = $data['applicant']['user_uuid']
            ?? $data['applicant']['user']['user_uuid']
            ?? $data['applicant']['exhibitor_administrator_uuid']
            ?? null;

        // Extract recipient information based on type
        $recipientUserId = $data['recipient']['user_id']
            ?? $data['recipient']['user']['user_id']
            ?? $data['recipient']['exhibitor_administrator_id']
            ?? null;
        $recipientUuid = $data['recipient']['user_uuid']
            ?? $data['recipient']['user']['user_uuid']
            ?? $data['recipient']['exhibitor_administrator_uuid']
            ?? null;

        $eventId = $data['basic_information']['event_id'] ?? 0;
//        dd($eventId, $applicantUserId, $applicantUuid, $recipientUserId, $recipientUuid);
        // Validate that we have all required data
        if (!$applicantUserId || !$applicantUuid || !$recipientUserId || !$recipientUuid) {
            return;
        }

        // Create or find matching record: applicant -> recipient
        $matchingUser = $this->query()
            ->where('owner_user_id', $applicantUserId)
            ->where('owner_uuid', $applicantUuid)
            ->where('peer_user_id', $recipientUserId)
            ->where('peer_uuid', $recipientUuid)
            ->where('event_id', $eventId)
            ->first();
        if (!$matchingUser) {
            $this->query()->create([
                'owner_user_id' => $applicantUserId,
                'owner_uuid' => $applicantUuid,
                'peer_user_id' => $recipientUserId,
                'peer_uuid' => $recipientUuid,
                'event_id' => $eventId,
                'status' => MatchingStatus::DEAL_DONE->value,
            ]);
        }

        // Create or find matching record: recipient -> applicant
        $matchingUser = $this->query()
            ->where('owner_user_id', $recipientUserId)
            ->where('owner_uuid', $recipientUuid)
            ->where('peer_user_id', $applicantUserId)
            ->where('peer_uuid', $applicantUuid)
            ->where('event_id', $eventId)
            ->first();

        if (!$matchingUser) {
            $this->query()->create([
                'owner_user_id' => $recipientUserId,
                'owner_uuid' => $recipientUuid,
                'peer_user_id' => $applicantUserId,
                'peer_uuid' => $applicantUuid,
                'event_id' => $eventId,
                'status' => MatchingStatus::DEAL_DONE->value,
            ]);
        }
    }
}
