<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveChatProfiles extends Authenticatable
{
    use Notifiable;
    protected $guarded = [];
    protected $table = 'live_chat_profiles';

    protected $casts = [
        'custom_fields' => 'array',
        'exhibitor_administrator_id' => 'integer',
    ];
}
