<?php

namespace App\Repositories;

use App\Models\BusinessAppointmentRoom;

class BusinessAppointmentRoomRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return BusinessAppointmentRoom::class;
    }

    public function findLocalizedRoomByBusinessAppointmentRoomId(
        int $roomId,
        int $languageId,
        int $fallbackLanguageId
    ): ?BusinessAppointmentRoom {
        return $this->query()
            ->where('business_appointment_room_id', $roomId)
            ->whereIn('language_id', [$languageId, $fallbackLanguageId])
            ->orderByRaw('CASE WHEN language_id = ? THEN 0 ELSE 1 END', [$languageId])
            ->orderBy('id')
            ->first();
    }

    public function isFreeRoom(int $roomId): bool
    {
        return $this->query()
            ->where('business_appointment_room_id', $roomId)
            ->where('is_free', true)
            ->exists();
    }
}
