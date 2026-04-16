<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceptionCheckin extends Model
{
    protected $guarded = [];

    protected $table = 'reception_checkins';

    protected $casts = [
        'checkin_at' => 'datetime',
        'first_checkin_at' => 'datetime',
        'second_checkin_at' => 'datetime',
    ];
}
