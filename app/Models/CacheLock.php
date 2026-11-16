<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CacheLock extends Model
{
    protected $table = 'cache_locks';

    protected $primaryKey = 'key';

    public $timestamps = false;

    protected $guarded = ['key'];
}
