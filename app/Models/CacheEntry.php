<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CacheEntry extends Model
{
    protected $table = 'cache';

    protected $primaryKey = 'key';

    public $timestamps = false;

    protected $guarded = ['key'];
}
