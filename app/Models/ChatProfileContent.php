<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatProfileContent extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $table = 'chat_profile_contents';

    protected $casts = [
        'language_setting' => 'array'
    ];
}
