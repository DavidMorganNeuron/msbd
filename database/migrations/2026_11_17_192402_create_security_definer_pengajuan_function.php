<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $ownerConnection = DB::connection('pgsql_owner');
        $schema = $ownerConnection->selectOne(
            "SELECT pg_get_userbyid(nspowner) AS owner FROM pg_namespace WHERE nspname = 'app_owner'"
        );

        if ($schema === null) {
            $ownerConnection->statement('CREATE SCHEMA app_owner AUTHORIZATION app_owner');
        } elseif ($schema->owner !== 'app_owner') {
            throw new RuntimeException('Schema app_owner exists but is not owned by app_owner.');
        }

        $ownerConnection->unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION app_owner.fn_buat_pengajuan_perubahan(
    p_id_siswa bigint,
    p_id_periode_update bigint,
    p_alasan text
)
RETURNS bigint
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $function$
DECLARE
    v_id_pengajuan bigint;
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM public.periode_update
         WHERE id_periode_update = p_id_periode_update
           AND status = 'aktif'
           AND CURRENT_DATE BETWEEN tanggal_mulai AND tanggal_selesai
    ) THEN
        RAISE EXCEPTION 'Periode update data belum dibuka.';
    END IF;

    INSERT INTO public.pengajuan_perubahan (
        id_siswa,
        id_periode_update,
        tgl_pengajuan,
        status_pengajuan,
        alasan
    )
    VALUES (
        p_id_siswa,
        p_id_periode_update,
        CURRENT_TIMESTAMP,
        'menunggu',
        p_alasan
    )
    RETURNING id_pengajuan INTO v_id_pengajuan;

    RETURN v_id_pengajuan;
END;
$function$;
SQL);
        $ownerConnection->statement('ALTER FUNCTION app_owner.fn_buat_pengajuan_perubahan(bigint, bigint, text) OWNER TO app_owner');
        $ownerConnection->statement('REVOKE ALL ON FUNCTION app_owner.fn_buat_pengajuan_perubahan(bigint, bigint, text) FROM PUBLIC');
        $ownerConnection->statement('GRANT USAGE ON SCHEMA app_owner TO role_siswa');
        $ownerConnection->statement('GRANT EXECUTE ON FUNCTION app_owner.fn_buat_pengajuan_perubahan(bigint, bigint, text) TO role_siswa');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection('pgsql_owner')->statement('DROP FUNCTION IF EXISTS app_owner.fn_buat_pengajuan_perubahan(bigint, bigint, text)');
    }
};
