<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveChatProfiles extends Authenticatable
{
    use Notifiable;
    protected $guarded = [];
    protected $table = 'live_chat_profiles';
}
