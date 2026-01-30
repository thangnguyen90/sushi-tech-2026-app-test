<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatProfileContent extends Model
{
    protected $guarded = [];
    protected $table = 'chat_profile_contents';

    protected $casts = [
        'language_setting' => 'array'
    ];
}
