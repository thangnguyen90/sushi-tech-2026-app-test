<?php

namespace App\Repositories;

use App\Models\LiveChatProfileTag;

class LiveChatProfileTagRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return LiveChatProfileTag::class;
    }
}
