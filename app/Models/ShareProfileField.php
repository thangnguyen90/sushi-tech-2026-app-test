<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShareProfileField extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'share_profile_contents';

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_required' => 'boolean',
        'is_uneditable' => 'boolean',
        'is_hidden' => 'boolean',
        'is_default' => 'boolean',
        'priority' => 'integer',
        'setting' => 'array',
        'selector_items' => 'array',
    ];
}
