<?php

namespace App\Repositories;

use App\Enums\AppointmentStatus;
use App\Enums\MatchingStatus;
use App\Models\LiveChatProfiles;
use App\Models\MatchingUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        ?int $ownerUserId,
        int $status,
        ?int $eventId = null
    ): Collection {
        $query = $this->query()
            ->select(['peer_uuid', 'updated_at'])
            ->where('owner_user_id', $ownerUserId)
            ->where(function (Builder $builder) use ($status): void {
                $builder->where(function (Builder $legacyQuery) use ($status): void {
                    $legacyQuery->where('status', $status)
                        ->whereNull('appointment_status');
                })->orWhere('appointment_status', AppointmentStatus::Approved->value);
            });

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
        int $status,
    ): bool {
        $liveChatProfileOwner = LiveChatProfiles::query()
            ->where('profile_id', $ownerUserId)
            ->first();

        $ownerUserId = $liveChatProfileOwner->user_id ?? $liveChatProfileOwner->exhibitor_administrator_id;

        $liveChatProfilePeer = LiveChatProfiles::query()
            ->whereIn('profile_id', $peerUserId)
            ->cursor()
            ->mapWithKeys(function ($item) {
                $key = $item->user_id ?? $item->exhibitor_administrator_id;

                return $key ? [$key => $item->uuid] : [];
            })
            ->all();

        $O2P = $this->query()
            ->where('owner_user_id', $ownerUserId)
            ->get()
            ->groupBy('peer_user_id')
            ->map(fn ($items) => $items->first())
            ->toArray();

        $eventId = config('eventos.event');
        foreach ($liveChatProfilePeer as $peerId => $peerUuid) {
            if (isset($O2P[$peerId]) && $O2P[$peerId]['status'] === config('constants.STATUS.DEAL_DONE')) {
                continue;
            }
            // Begin transaction to ensure atomicity
            DB::beginTransaction();
            try {
                // Update owner -> peer
                $this->updateOrCreate(
                    [
                        'owner_user_id' => $ownerUserId,
                        'owner_uuid' => $liveChatProfileOwner->uuid,
                        'peer_user_id' => $peerId,
                        'peer_uuid' => $peerUuid,
                        'event_id' => $eventId,
                    ],
                    [
                        'status' => $status,
                    ]
                );
                // Update peer -> owner
                $this->updateOrCreate(
                    [
                        'owner_user_id' => $peerId,
                        'owner_uuid' => $peerUuid,
                        'peer_user_id' => $ownerUserId,
                        'peer_uuid' => $liveChatProfileOwner->uuid,
                        'event_id' => $eventId,
                    ],
                    [
                        'status' => $status,
                    ]
                );
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
            }
        }

        return true;
    }

    public function addOrUpdateDataFromWebhook(array $data): void
    {
        if (! isset($data['module_code'])) {
            $data['module_code'] = 'BusinessAppointmentApproved';
        }

        $appointmentStatus = AppointmentStatus::fromModuleCode((string) $data['module_code']);
        $appointmentScheduleId = $this->parseInteger(
            data_get($data, 'exhibitor_administrator_appointment_schedule_detail.id')
        );
        $eventId = $this->parseInteger(data_get($data, 'basic_information.event_id'));
        $applicant = $this->extractParticipant($data['applicant'] ?? null);
        $recipient = $this->extractParticipant($data['recipient'] ?? null);

        if (
            $appointmentStatus === null
            || $appointmentScheduleId === null
            || $eventId === null
            || $applicant === null
            || $recipient === null
        ) {
            return;
        }

        $sharedValues = [
            'appointment_schedule_id' => $appointmentScheduleId,
            'appointment_status' => $appointmentStatus->value,
            'webhook_module_code' => (string) $data['module_code'],
            'webhook_schedule_status' => $this->parseString(
                data_get($data, 'exhibitor_administrator_appointment_schedule_detail.status')
            ),
            'webhook_data' => $data,
        ];

        $businessAppointmentRoomId = $this->parseInteger(
            data_get($data, 'exhibitor_administrator_appointment_schedule_detail.business_appointment_room_id')
        );
        if ($businessAppointmentRoomId !== null) {
            $sharedValues['business_appointment_room_id'] = $businessAppointmentRoomId;
        }

        $scheduleStartDatetime = data_get(
            $data,
            'exhibitor_administrator_appointment_schedule_detail.schedule_start_datetime'
        );
        if (is_string($scheduleStartDatetime) && $scheduleStartDatetime !== '') {
            $sharedValues['schedule_start_datetime'] = $scheduleStartDatetime;
        }

        $this->upsertAppointmentRecord(
            owner: $applicant,
            peer: $recipient,
            eventId: $eventId,
            appointmentStatus: $appointmentStatus,
            values: $sharedValues
        );

        $this->upsertAppointmentRecord(
            owner: $recipient,
            peer: $applicant,
            eventId: $eventId,
            appointmentStatus: $appointmentStatus,
            values: $sharedValues
        );
    }

    /**
     * @param  array{id: int, uuid: string}  $owner
     * @param  array{id: int, uuid: string}  $peer
     * @param  array<string, mixed>  $values
     */
    private function upsertAppointmentRecord(
        array $owner,
        array $peer,
        int $eventId,
        AppointmentStatus $appointmentStatus,
        array $values
    ): void {
        $matchingUser = $this->query()
            ->where('owner_user_id', $owner['id'])
            ->where('peer_user_id', $peer['id'])
            ->where('event_id', $eventId)
            ->where(function (Builder $builder) use ($values): void {
                $builder
                    ->where('appointment_schedule_id', $values['appointment_schedule_id'])
                    ->orWhereNull('appointment_schedule_id');
            })
            ->orderByDesc('appointment_schedule_id')
            ->first();

        if (! $matchingUser) {
            $matchingUser = new MatchingUser;
            $matchingUser->status = $appointmentStatus === AppointmentStatus::Approved
                ? MatchingStatus::DEAL_DONE->value
                : MatchingStatus::PENDING->value;
        } elseif ($appointmentStatus === AppointmentStatus::Approved) {
            $matchingUser->status = MatchingStatus::DEAL_DONE->value;
        }

        $matchingUser->fill([
            'owner_user_id' => $owner['id'],
            'owner_uuid' => $owner['uuid'],
            'peer_user_id' => $peer['id'],
            'peer_uuid' => $peer['uuid'],
            'event_id' => $eventId,
            ...$values,
        ]);

        $matchingUser->save();
    }

    /**
     * @return array{id: int, uuid: string}|null
     */
    private function extractParticipant(mixed $participant): ?array
    {
        if (! is_array($participant)) {
            return null;
        }

        $participantId = $this->parseInteger(
            data_get($participant, 'user_id')
            ?? data_get($participant, 'user.user_id')
            ?? data_get($participant, 'exhibitor_administrator_id')
            ?? data_get($participant, 'exhibitor_administrator.exhibitor_administrator_id')
        );

        $participantUuid = data_get($participant, 'user_uuid')
            ?? data_get($participant, 'user.user_uuid')
            ?? data_get($participant, 'exhibitor_administrator_uuid')
            ?? data_get($participant, 'exhibitor_administrator.exhibitor_administrator_uuid');

        if ($participantId === null || ! is_string($participantUuid) || $participantUuid === '') {
            return null;
        }

        return [
            'id' => $participantId,
            'uuid' => $participantUuid,
        ];
    }

    private function parseInteger(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function parseString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
