<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target ERD model. Its backing table is created by the target-extension migration. */
class VerifikasiPendaftaran extends Model
{
    protected $table = 'verifikasi_pendaftaran';

    protected $primaryKey = 'id_verifikasi';

    public $timestamps = false;

    protected $guarded = ['id_verifikasi'];
}
