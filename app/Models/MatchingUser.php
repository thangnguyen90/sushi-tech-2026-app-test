<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchingUser extends Model
{
    protected $guarded = [];

    protected $table = 'matching_users';

    protected $casts = [
        'schedule_start_datetime' => 'datetime',
        'webhook_data' => 'array',
    ];
}
