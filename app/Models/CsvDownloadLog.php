<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsvDownloadLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_uuid',
        'ip_address',
        'downloaded_user_count',
        'live_chat_user_uuids',
        'is_suspicious',
        'suspicious_flags',
        'http_status_code',
        'status',
        'error_message',
    ];

    protected $casts = [
        'downloaded_user_count' => 'integer',
        'live_chat_user_uuids'  => 'array',
        'is_suspicious'         => 'boolean',
        'suspicious_flags'      => 'array',
        'http_status_code'      => 'integer',
        'created_at'            => 'datetime',
    ];
}
