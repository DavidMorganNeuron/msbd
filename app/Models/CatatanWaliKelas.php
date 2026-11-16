<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target ERD model. Its backing table is created by the target-extension migration. */
class CatatanWaliKelas extends Model
{
    protected $table = 'catatan_wali_kelas';

    protected $primaryKey = 'id_catatan';

    public $timestamps = false;

    protected $guarded = ['id_catatan'];
}
