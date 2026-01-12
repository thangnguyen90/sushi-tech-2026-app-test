<?php

namespace App\Repositories;

use App\Models\LiveChatTag;

class LiveChatTagRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return LiveChatTag::class;
    }
}
