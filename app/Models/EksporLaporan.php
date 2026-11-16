<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target ERD model. Its backing table is created by the target-extension migration. */
class EksporLaporan extends Model
{
    protected $table = 'ekspor_laporan';

    protected $primaryKey = 'id_ekspor';

    public $timestamps = false;

    protected $guarded = ['id_ekspor'];
}
