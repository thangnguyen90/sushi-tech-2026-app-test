<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchingCsvDownloadSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
