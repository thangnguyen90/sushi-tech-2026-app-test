<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessAppointmentRoom extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'business_appointment_rooms';

    protected $casts = [
        'room_image' => 'array',
    ];
}
