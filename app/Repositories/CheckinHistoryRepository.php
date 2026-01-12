<?php

namespace App\Repositories;

use App\Models\CheckinHistory;

class CheckinHistoryRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return CheckinHistory::class;
    }
}
