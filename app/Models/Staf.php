<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staf extends Model
{
    protected $table = 'staf';

    protected $primaryKey = 'id_staf';

    public $timestamps = false;

    protected $guarded = ['id_staf'];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'id_pengguna', 'id_pengguna');
    }
}
