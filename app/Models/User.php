<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $guarded = [];
    protected $casts = [
        'is_first_login' => 'boolean',
        'webhook_data' => 'array',
    ];
}
