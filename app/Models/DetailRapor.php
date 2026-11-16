<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target ERD model. Its backing table is created by the target-extension migration. */
class DetailRapor extends Model
{
    protected $table = 'detail_rapor';

    protected $primaryKey = 'id_detail_rapor';

    public $timestamps = false;

    protected $guarded = ['id_detail_rapor'];
}
