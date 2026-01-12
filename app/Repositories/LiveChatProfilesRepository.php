<?php

namespace App\Repositories;

use App\Models\LiveChatProfiles;

class LiveChatProfilesRepository extends BaseRepository
{

    protected function modelClass(): string
    {
        return LiveChatProfiles::class;
    }
}
