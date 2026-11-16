<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target ERD model. Its backing table is created by the target-extension migration. */
class Kecamatan extends Model
{
    protected $table = 'kecamatan';

    protected $primaryKey = 'id_kecamatan';

    public $timestamps = false;

    protected $guarded = ['id_kecamatan'];
}
