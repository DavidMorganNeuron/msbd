<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tingkat extends Model
{
    protected $table = 'tingkat';

    protected $primaryKey = 'id_tingkat';

    public $timestamps = false;

    protected $guarded = ['id_tingkat'];

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'id_tingkat', 'id_tingkat');
    }
}
