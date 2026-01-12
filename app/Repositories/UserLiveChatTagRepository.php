<?php

namespace App\Repositories;

use App\Models\UserLiveChatTag;

class UserLiveChatTagRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return UserLiveChatTag::class;
    }
}
