<?php

namespace App\Repositories;

use App\Enums\AppointmentStatus;
use App\Enums\MatchingStatus;
use App\Models\LiveChatProfiles;
use App\Models\MatchingUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
     * @param  array<string, mixed>  $row
     */
    public function addOrUpdateDataFromCsvRow(array $row): bool
    {
        $payload = $this->buildWebhookPayloadFromCsvRow($row);

        if ($payload === null) {
            return false;
        }

        $appointmentStatus = AppointmentStatus::fromModuleCode((string) $payload['module_code']);
        $appointmentScheduleId = $this->parseInteger(
            data_get($payload, 'exhibitor_administrator_appointment_schedule_detail.id')
        );
        $eventId = $this->parseInteger(data_get($payload, 'basic_information.event_id'));
        $applicant = $this->extractParticipant($payload['applicant'] ?? null);
        $recipient = $this->extractParticipant($payload['recipient'] ?? null);

        if (
            $appointmentStatus === null
            || $appointmentScheduleId === null
            || $eventId === null
            || $applicant === null
            || $recipient === null
        ) {
            return false;
        }

        $sharedValues = [
            'appointment_schedule_id' => $appointmentScheduleId,
            'appointment_status' => $appointmentStatus->value,
            'webhook_module_code' => (string) $payload['module_code'],
            'webhook_schedule_status' => $this->parseString(
                data_get($payload, 'exhibitor_administrator_appointment_schedule_detail.status')
            ),
            'webhook_data' => $payload,
        ];

        $businessAppointmentRoomId = $this->parseInteger(
            data_get($payload, 'exhibitor_administrator_appointment_schedule_detail.business_appointment_room_id')
        );
        if ($businessAppointmentRoomId !== null) {
            $sharedValues['business_appointment_room_id'] = $businessAppointmentRoomId;
        }

        $scheduleStartDatetime = data_get(
            $payload,
            'exhibitor_administrator_appointment_schedule_detail.schedule_start_datetime'
        );
        if (is_string($scheduleStartDatetime) && $scheduleStartDatetime !== '') {
            $sharedValues['schedule_start_datetime'] = $scheduleStartDatetime;
        }

        $didUpdateApplicant = $this->backfillAppointmentRecord(
            owner: $applicant,
            peer: $recipient,
            eventId: $eventId,
            appointmentStatus: $appointmentStatus,
            values: $sharedValues
        );

        $didUpdateRecipient = $this->backfillAppointmentRecord(
            owner: $recipient,
            peer: $applicant,
            eventId: $eventId,
            appointmentStatus: $appointmentStatus,
            values: $sharedValues
        );

        return $didUpdateApplicant || $didUpdateRecipient;
    }

    /**
     * @return array<int, array{
     *     appointment_id: int|string|null,
     *     partner_name: string|null,
     *     schedule_time: string|null,
     *     room_id: int|null,
     *     room_name: string,
     *     checkin_status: string|null,
     *     user_uuid: string|null
     * }>
     */
    public function getApprovedAppointmentsForRoom(int $roomId, int $ownerUserId, int $languageId): array
    {
        $fallbackLanguageId = (int) config('language.jpn', 1);
        $latestReceptionCheckins = DB::table('reception_checkins')
            ->selectRaw('MAX(id) as latest_id, appointment_schedule_id')
            ->whereNotNull('appointment_schedule_id')
            ->groupBy('appointment_schedule_id');

        $rows = $this->query()
            ->from('matching_users')
            ->select('matching_users.*')
            ->selectRaw('COALESCE(requested_rooms.name, fallback_rooms.name, ?) as room_name', [''])
            ->selectRaw('peer_profiles.nickname as peer_nickname')
            ->addSelect([
                'latest_reception_checkins.checkin_status as latest_checkin_status',
                'latest_reception_checkins.user_uuid as latest_checkin_user_uuid',
            ])
            ->leftJoin('business_appointment_rooms as requested_rooms', function ($join) use ($languageId): void {
                $join->on(
                    'requested_rooms.business_appointment_room_id',
                    '=',
                    'matching_users.business_appointment_room_id'
                )->where('requested_rooms.language_id', '=', $languageId);
            })
            ->leftJoin('business_appointment_rooms as fallback_rooms', function ($join) use ($fallbackLanguageId): void {
                $join->on(
                    'fallback_rooms.business_appointment_room_id',
                    '=',
                    'matching_users.business_appointment_room_id'
                )->where('fallback_rooms.language_id', '=', $fallbackLanguageId);
            })
            ->leftJoin('live_chat_profiles as peer_profiles', function ($join): void {
                $join->on('peer_profiles.user_id', '=', 'matching_users.peer_user_id')
                    ->whereNull('peer_profiles.deleted_at');
            })
            ->leftJoinSub($latestReceptionCheckins, 'latest_reception_checkin_ids', function ($join): void {
                $join->on(
                    'latest_reception_checkin_ids.appointment_schedule_id',
                    '=',
                    'matching_users.appointment_schedule_id'
                );
            })
            ->leftJoin('reception_checkins as latest_reception_checkins', function ($join): void {
                $join->on('latest_reception_checkins.id', '=', 'latest_reception_checkin_ids.latest_id');
            })
            ->where('matching_users.owner_user_id', $ownerUserId)
            ->where('matching_users.appointment_status', AppointmentStatus::Approved->value)
            ->whereNotNull('matching_users.appointment_schedule_id')
            ->orderBy('matching_users.schedule_start_datetime')
            ->orderBy('matching_users.appointment_schedule_id')
            ->get();

        return $rows->map(function (MatchingUser $matchingUser): array {
            $scheduleTime = $matchingUser->schedule_start_datetime;

            return [
                'appointment_id' => $matchingUser->appointment_schedule_id,
                'partner_name' => $matchingUser->getAttribute('peer_nickname')
                    ?? $this->resolvePartnerName($matchingUser),
                'schedule_time' => $scheduleTime instanceof Carbon
                    ? $scheduleTime->format('Y-m-d H:i:s')
                    : null,
                'room_id' => $matchingUser->business_appointment_room_id !== null
                    ? (int) $matchingUser->business_appointment_room_id
                    : null,
                'room_name' => (string) ($matchingUser->getAttribute('room_name') ?? ''),
                'checkin_status' => $matchingUser->getAttribute('latest_checkin_status'),
                'user_uuid' => $matchingUser->getAttribute('latest_checkin_user_uuid'),
            ];
        })->all();
    }

    public function findApprovedAppointmentByScheduleId(int $appointmentScheduleId): ?MatchingUser
    {
        return $this->query()
            ->where('appointment_schedule_id', $appointmentScheduleId)
            ->where('appointment_status', AppointmentStatus::Approved->value)
            ->orderBy('id')
            ->first();
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
     * @param  array{id: int, uuid: string}  $owner
     * @param  array{id: int, uuid: string}  $peer
     * @param  array<string, mixed>  $values
     */
    private function backfillAppointmentRecord(
        array $owner,
        array $peer,
        int $eventId,
        AppointmentStatus $appointmentStatus,
        array $values
    ): bool {
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
            ->orderByDesc('id')
            ->first();

        if (! $matchingUser) {
            $matchingUser = new MatchingUser;
            $matchingUser->status = $appointmentStatus === AppointmentStatus::Approved
                ? MatchingStatus::DEAL_DONE->value
                : MatchingStatus::PENDING->value;
        }

        if ($appointmentStatus === AppointmentStatus::Approved) {
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

        return true;
    }

    /**
     * @return array{id: int, uuid: string}|null
     */
    private function extractParticipant(mixed $participant): ?array
    {
        if (! is_array($participant)) {
            return null;
        }

        $participantType = null;
        $participantId = null;

        $userId = $this->parseInteger(
            data_get($participant, 'user_id')
            ?? data_get($participant, 'user.user_id')
        );
        if ($userId !== null) {
            $participantId = $userId;
            $participantType = 'user';
        }

        if ($participantId === null) {
            $exhibitorAdministratorId = $this->parseInteger(
                data_get($participant, 'exhibitor_administrator_id')
                ?? data_get($participant, 'exhibitor_administrator.exhibitor_administrator_id')
            );
            if ($exhibitorAdministratorId !== null) {
                $participantId = $exhibitorAdministratorId;
                $participantType = 'exhibitor_administrator';
            }
        }

        $participantUuid = $this->resolveLiveChatProfileUuid($participantId, $participantType)
            ?? data_get($participant, 'user_uuid')
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

    private function resolveLiveChatProfileUuid(?int $participantId, ?string $participantType): ?string
    {
        if ($participantId === null || $participantType === null) {
            return null;
        }

        $profile = LiveChatProfiles::query()
            ->whereNull('deleted_at')
            ->when(
                $participantType === 'user',
                fn (Builder $builder) => $builder->where('user_id', $participantId),
                fn (Builder $builder) => $builder->where('exhibitor_administrator_id', $participantId)
            )
            ->orderByDesc('id')
            ->first();

        $uuid = $profile?->uuid;

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    private function resolvePartnerName(MatchingUser $matchingUser): ?string
    {
        $webhookData = $matchingUser->webhook_data;
        if (! is_array($webhookData)) {
            return null;
        }

        $ownerUuid = (string) ($matchingUser->owner_uuid ?? '');
        $applicantUuid = $this->extractWebhookParticipantUuid($webhookData['applicant'] ?? null);
        $recipientUuid = $this->extractWebhookParticipantUuid($webhookData['recipient'] ?? null);

        if ($ownerUuid !== '' && $ownerUuid === $applicantUuid) {
            return $this->extractWebhookParticipantName(
                $webhookData,
                'recipient',
                'recipient_name'
            );
        }

        if ($ownerUuid !== '' && $ownerUuid === $recipientUuid) {
            return $this->extractWebhookParticipantName(
                $webhookData,
                'applicant',
                'applicant_name'
            );
        }

        return null;
    }

    private function extractWebhookParticipantUuid(mixed $participant): ?string
    {
        if (! is_array($participant)) {
            return null;
        }

        $uuid = data_get($participant, 'user_uuid')
            ?? data_get($participant, 'user.user_uuid')
            ?? data_get($participant, 'exhibitor_administrator_uuid')
            ?? data_get($participant, 'exhibitor_administrator.exhibitor_administrator_uuid');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    private function extractWebhookParticipantName(
        array $webhookData,
        string $participantKey,
        string $scheduleNameKey
    ): ?string {
        $scheduleName = data_get(
            $webhookData,
            'exhibitor_administrator_appointment_schedule_detail.'.$scheduleNameKey
        );
        if (is_string($scheduleName) && $scheduleName !== '') {
            return $scheduleName;
        }

        $participant = $webhookData[$participantKey] ?? null;
        if (! is_array($participant)) {
            return null;
        }

        $name = data_get($participant, 'name')
            ?? data_get($participant, 'user.name')
            ?? data_get($participant, 'exhibitor_administrator.name');

        return is_string($name) && $name !== '' ? $name : null;
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

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function buildWebhookPayloadFromCsvRow(array $row): ?array
    {
        $status = $this->parseString($row['status'] ?? null);
        $moduleCode = $this->resolveModuleCodeFromCsvStatus($status);
        $appointmentScheduleId = $this->parseInteger($row['id'] ?? null);
        $eventId = $this->parseInteger($row['event_id'] ?? null);
        $applicant = $this->buildParticipantPayloadFromCsvRow($row, 'applicant');
        $recipient = $this->buildParticipantPayloadFromCsvRow($row, 'recipient');

        if (
            $moduleCode === null
            || $status === null
            || $appointmentScheduleId === null
            || $eventId === null
            || $applicant === null
            || $recipient === null
        ) {
            return null;
        }

        $payload = [
            'module_code' => $moduleCode,
            'basic_information' => [
                'event_id' => $eventId,
            ],
            'applicant' => $applicant,
            'recipient' => $recipient,
            'exhibitor_administrator_appointment_schedule_detail' => [
                'id' => $appointmentScheduleId,
                'status' => $status,
                'applicant_name' => $this->parseString($row['applicant_name'] ?? null),
                'recipient_name' => $this->parseString($row['recipient_name'] ?? null),
            ],
        ];

        $businessAppointmentRoomId = $this->parseInteger($row['business_appointment_room_id'] ?? null);
        if ($businessAppointmentRoomId !== null) {
            $payload['exhibitor_administrator_appointment_schedule_detail']['business_appointment_room_id'] = $businessAppointmentRoomId;
        }

        $scheduleStartDatetime = $this->parseDateTimeString($row['schedule_start_datetime'] ?? null);
        if ($scheduleStartDatetime !== null) {
            $payload['exhibitor_administrator_appointment_schedule_detail']['schedule_start_datetime'] = $scheduleStartDatetime;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function buildParticipantPayloadFromCsvRow(array $row, string $prefix): ?array
    {
        $participantUuid = $this->parseString($row["{$prefix}_chat_uuid"] ?? null);
        $participantName = $this->parseString($row["{$prefix}_name"] ?? null);
        $participantCompany = $this->parseString($row["{$prefix}_company_name"] ?? null);
        $participantMailAddress = $this->parseString($row["{$prefix}_email"] ?? null);

        if ($participantUuid === null) {
            return null;
        }

        $visitorId = $this->parseInteger($row["{$prefix}_visitor_id"] ?? null);
        if ($visitorId !== null) {
            return array_filter([
                'user_id' => $visitorId,
                'user_uuid' => $participantUuid,
                'name' => $participantName,
                'company_name' => $participantCompany,
                'mail_address' => $participantMailAddress,
            ], static fn (mixed $value): bool => $value !== null);
        }

        $exhibitorAdministratorId = $this->parseInteger($row["{$prefix}_exhibitor_administrator_id"] ?? null);
        if ($exhibitorAdministratorId !== null) {
            return array_filter([
                'exhibitor_administrator_id' => $exhibitorAdministratorId,
                'exhibitor_administrator_uuid' => $participantUuid,
                'name' => $participantName,
                'company_name' => $participantCompany,
                'mail_address' => $participantMailAddress,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return null;
    }

    private function resolveModuleCodeFromCsvStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return match (strtolower($status)) {
            'approved' => 'BusinessAppointmentApproved',
            'cancel', 'canceled', 'cancelled' => 'BusinessAppointmentCancel',
            'reject', 'rejected' => 'BusinessAppointmentReject',
            'pending' => 'BusinessAppointment',
            'reschedule', 'rescheduled' => 'BusinessAppointmentReschedule',
            default => null,
        };
    }

    private function parseDateTimeString(mixed $value): ?string
    {
        $value = $this->parseString($value);

        if ($value === null) {
            return null;
        }

        $formats = [
            'Y-n-j, G:i',
            'Y-n-j, H:i',
            'Y-m-d H:i:s',
            DATE_ATOM,
        ];

        $jst = new \DateTimeZone('Asia/Tokyo');
        $utc = new \DateTimeZone('UTC');

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, $jst);

            if ($date instanceof \DateTimeImmutable) {
                return $date->setTimezone($utc)->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($value.' +0900');

        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
