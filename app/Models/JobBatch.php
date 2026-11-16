<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobBatch extends Model
{
    protected $table = 'job_batches';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
