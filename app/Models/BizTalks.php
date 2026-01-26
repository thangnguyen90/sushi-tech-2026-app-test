<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Ramsey\Uuid\Uuid;


class BizTalks extends Model
{
    use HasUuids;

    protected $table = 'biz_talks';
    protected $guarded = [];
    public $timestamps = false;
    public function newUniqueId(): string
    {
        return (string) Uuid::uuid7();
    }

}
