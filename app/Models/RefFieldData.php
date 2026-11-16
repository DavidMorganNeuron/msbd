<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Target ERD model. Its backing table is created by the target-extension migration. */
class RefFieldData extends Model
{
    protected $table = 'ref_field_data';

    protected $primaryKey = 'id_field';

    public $timestamps = false;

    protected $guarded = ['id_field'];
}
