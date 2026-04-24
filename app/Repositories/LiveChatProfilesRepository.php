<?php

namespace App\Repositories;

use App\Models\LiveChatProfiles;

class LiveChatProfilesRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return LiveChatProfiles::class;
    }

    public function getProfileByUserId(?int $id): ?LiveChatProfiles
    {
        return $this->query()
            ->where('user_id', $id)
            ->first();
    }

    public function getProfileByUuid(?string $uuid): ?LiveChatProfiles
    {
        return $this->query()
            ->where('uuid', $uuid)
            ->first();
    }

    public function getProfileByUserUuid(?string $userUuid): ?LiveChatProfiles
    {
        return $this->query()
            ->where('user_uuid', $userUuid)
            ->first();
    }
}
