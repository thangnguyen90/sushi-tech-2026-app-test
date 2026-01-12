<?php

namespace App\Repositories;

use App\Models\LiveChatTagContent;

class LiveChatTagContentRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return LiveChatTagContent::class;
    }
}
