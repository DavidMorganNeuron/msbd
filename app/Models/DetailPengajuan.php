<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPengajuan extends Model
{
    protected $table = 'detail_pengajuan';

    protected $primaryKey = 'id_detail_pengajuan';

    public $timestamps = false;

    protected $guarded = ['id_detail_pengajuan'];

    protected function casts(): array
    {
        return [
            'nilai_lama' => 'json',
            'nilai_baru' => 'json',
        ];
    }

    public function pengajuanPerubahan(): BelongsTo
    {
        return $this->belongsTo(PengajuanPerubahan::class, 'id_pengajuan', 'id_pengajuan');
    }
}
