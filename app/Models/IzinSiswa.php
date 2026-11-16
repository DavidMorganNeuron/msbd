<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target ERD model. Its backing table is created by the target-extension migration. */
class IzinSiswa extends Model
{
    protected $table = 'izin_siswa';

    protected $primaryKey = 'id_izin';

    public $timestamps = false;

    protected $guarded = ['id_izin'];
}
