<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('sql'.DIRECTORY_SEPARATOR.'dcl'.DIRECTORY_SEPARATOR.'90_hapus_role_guru.sql');

        if (! is_file($path)) {
            throw new RuntimeException("File SQL penghapusan role guru tidak ditemukan: {$path}");
        }

        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("File SQL penghapusan role guru tidak dapat dibaca: {$path}");
        }

        DB::unprepared($sql);
    }

    /**
     * Restoring the removed role and prior account assignments requires restoring the pre-migration backup.
     */
    public function down(): void {}
};
