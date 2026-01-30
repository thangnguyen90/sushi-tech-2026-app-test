<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class LiveChatProfileFieldOption extends Model
{
    use Notifiable;
    protected $guarded = [];
    protected $table = 'live_chat_profile_field_options';
}
