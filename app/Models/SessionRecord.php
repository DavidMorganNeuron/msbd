<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionRecord extends Model
{
    protected $table = 'sessions';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
