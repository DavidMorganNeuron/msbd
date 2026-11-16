<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
