<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'role';

    protected $primaryKey = 'id_role';

    public $timestamps = false;

    protected $guarded = ['id_role'];

    public function kode(): string
    {
        $kode = mb_strtolower(trim((string) ($this->kode_role ?: $this->nama_role)));

        return match ($kode) {
            'tata usaha' => 'tu',
            'wali kelas' => 'wali_kelas',
            'kepala sekolah' => 'kepsek',
            default => $kode,
        };
    }

    public function pengguna(): HasMany
    {
        return $this->hasMany(Pengguna::class, 'id_role', 'id_role');
    }

}
