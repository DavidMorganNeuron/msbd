<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target ERD model. Its backing table is created by the target-extension migration. */
class Kelurahan extends Model
{
    protected $table = 'kelurahan';

    protected $primaryKey = 'id_kelurahan';

    public $timestamps = false;

    protected $guarded = ['id_kelurahan'];
}
