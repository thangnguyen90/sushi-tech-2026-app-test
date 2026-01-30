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
            ->orWhereNull('exhibitor_administrator_id', $id)
            ->first();
    }
}
