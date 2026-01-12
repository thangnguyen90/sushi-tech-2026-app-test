<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveChatProfileTag extends Model
{
    protected $table = 'live_chat_profile_tags';

    public $timestamps = true;

    protected $fillable = [
        'user_live_chat_profile_id',
        'live_chat_data_source_id',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
