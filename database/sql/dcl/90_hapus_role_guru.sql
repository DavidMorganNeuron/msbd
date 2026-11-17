-- Pindahkan akun dan hak lama ke wali_kelas sebelum menghapus role guru.
DO $$
DECLARE
    guru_role_id bigint;
    wali_kelas_role_id bigint;
    migrated_account_count bigint := 0;
    disabled_account_count bigint := 0;
    deleted_session_count bigint := 0;
    deleted_token_count bigint := 0;
BEGIN
    SELECT id_role
    INTO guru_role_id
    FROM public.role
    WHERE nama_role = 'guru'
    ORDER BY id_role
    LIMIT 1;

    IF guru_role_id IS NULL THEN
        RAISE NOTICE 'Role guru sudah tidak ada; tidak ada perubahan.';
        RETURN;
    END IF;

    SELECT id_role
    INTO wali_kelas_role_id
    FROM public.role
    WHERE nama_role = 'wali_kelas'
    ORDER BY id_role
    LIMIT 1;

    IF wali_kelas_role_id IS NULL THEN
        RAISE EXCEPTION 'Role wali_kelas wajib tersedia sebelum role guru dihapus.';
    END IF;

    INSERT INTO public.role_hak_akses (id_role, id_hak_akses)
    SELECT DISTINCT wali_kelas_role_id, guru_access.id_hak_akses
    FROM public.role_hak_akses AS guru_access
    WHERE guru_access.id_role = guru_role_id
      AND NOT EXISTS (
          SELECT 1
          FROM public.role_hak_akses AS wali_access
          WHERE wali_access.id_role = wali_kelas_role_id
            AND wali_access.id_hak_akses = guru_access.id_hak_akses
      )
    ON CONFLICT DO NOTHING;

    SELECT count(*)
    INTO disabled_account_count
    FROM public.pengguna AS p
    WHERE p.id_role = guru_role_id
      AND p.is_aktif
      AND NOT EXISTS (
          SELECT 1
          FROM public.guru AS g
          JOIN public.penugasan_wali_kelas AS pw
            ON pw.id_guru = g.id_guru
          JOIN public.semester AS s
            ON s.id_semester = pw.id_semester
           AND s.is_aktif
          WHERE g.id_pengguna = p.id_pengguna
            AND g.is_aktif
      );

    IF to_regclass('public.sessions') IS NOT NULL THEN
        EXECUTE '
            DELETE FROM public.sessions
            WHERE user_id IN (
                SELECT id_pengguna
                FROM public.pengguna
                WHERE id_role = $1
            )'
        USING guru_role_id;
        GET DIAGNOSTICS deleted_session_count = ROW_COUNT;
    END IF;

    IF to_regclass('public.personal_access_tokens') IS NOT NULL THEN
        EXECUTE '
            DELETE FROM public.personal_access_tokens
            WHERE tokenable_type = $1
              AND tokenable_id IN (
                  SELECT id_pengguna
                  FROM public.pengguna
                  WHERE id_role = $2
              )'
        USING 'App\Models\Pengguna', guru_role_id;
        GET DIAGNOSTICS deleted_token_count = ROW_COUNT;
    END IF;

    UPDATE public.pengguna AS p
    SET id_role = wali_kelas_role_id,
        is_aktif = p.is_aktif AND EXISTS (
            SELECT 1
            FROM public.guru AS g
            JOIN public.penugasan_wali_kelas AS pw
              ON pw.id_guru = g.id_guru
            JOIN public.semester AS s
              ON s.id_semester = pw.id_semester
             AND s.is_aktif
            WHERE g.id_pengguna = p.id_pengguna
              AND g.is_aktif
        ),
        diubah_pada = now()
    WHERE p.id_role = guru_role_id;

    GET DIAGNOSTICS migrated_account_count = ROW_COUNT;

    DELETE FROM public.role_hak_akses
    WHERE id_role = guru_role_id;

    DELETE FROM public.role
    WHERE id_role = guru_role_id;

    RAISE NOTICE 'Akun dipindahkan: %, akun tanpa penugasan dinonaktifkan: %, sesi terkait dihapus: %, token terkait dihapus: %.',
        migrated_account_count,
        disabled_account_count,
        deleted_session_count,
        deleted_token_count;
END
$$;
