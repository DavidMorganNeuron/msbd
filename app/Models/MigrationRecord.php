<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationRecord extends Model
{
    protected $table = 'migrations';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = ['id'];
}
