-- Apply the approved status_siswa ID mapping only after its full precondition is verified.
DO $fase1$
DECLARE
    v_before_mapping boolean;
    v_after_mapping boolean;
    v_reference_count integer;
    v_student_count integer;
    v_students_active integer;
    v_students_transfer integer;
    v_approved_transfer_count integer;
    v_updated_rows integer;
BEGIN
    IF current_database() NOT IN ('msbd_tubes_kel7_staging', 'msbd_latihan_utama', 'msbd_tubes_kel7') THEN
        RAISE EXCEPTION 'Perbaikan status_siswa ditolak pada database %', current_database();
    END IF;

    IF current_database() = 'msbd_latihan_utama'
       AND NOT EXISTS (
           SELECT 1 FROM pg_database
           WHERE datname = current_database()
             AND shobj_description(oid, 'pg_database') = 'FASE1_TRAINING_CLONE'
       ) THEN
        RAISE EXCEPTION 'Database latihan tidak memiliki marker FASE1_TRAINING_CLONE';
    END IF;

    IF current_database() = 'msbd_tubes_kel7'
       AND current_setting('fase1.confirmed_target', true) IS DISTINCT FROM current_database() THEN
        RAISE EXCEPTION 'Database utama memerlukan konfirmasi target dari runner Fase 1';
    END IF;

    IF (
        SELECT count(*)
        FROM pg_attribute a
        JOIN pg_class c ON c.oid = a.attrelid
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public'
          AND c.relkind IN ('r', 'p')
          AND a.attname = 'id_status_siswa'
          AND a.attnum > 0
          AND NOT a.attisdropped
    ) <> 2
    OR NOT EXISTS (
        SELECT 1
        FROM pg_attribute a
        WHERE a.attrelid = 'public.siswa'::regclass
          AND a.attname = 'id_status_siswa'
          AND a.attnum > 0
          AND NOT a.attisdropped
    )
    OR NOT EXISTS (
        SELECT 1
        FROM pg_attribute a
        WHERE a.attrelid = 'public.status_siswa'::regclass
          AND a.attname = 'id_status_siswa'
          AND a.attnum > 0
          AND NOT a.attisdropped
    ) THEN
        RAISE EXCEPTION 'Kolom referensi id_status_siswa berbeda dari hasil preflight yang disetujui';
    END IF;

    SELECT count(*)
    INTO v_reference_count
    FROM pg_constraint c
    WHERE c.contype = 'f'
      AND c.confrelid = 'public.status_siswa'::regclass
      AND NOT (
          c.conrelid = 'public.siswa'::regclass
          AND c.conkey = ARRAY[
              (SELECT attnum FROM pg_attribute WHERE attrelid = 'public.siswa'::regclass AND attname = 'id_status_siswa')
          ]::smallint[]
          AND c.confkey = ARRAY[
              (SELECT attnum FROM pg_attribute WHERE attrelid = 'public.status_siswa'::regclass AND attname = 'id_status_siswa')
          ]::smallint[]
      );

    IF v_reference_count <> 0 THEN
        RAISE EXCEPTION 'Ada FK lain yang merujuk status_siswa; perubahan dibatalkan';
    END IF;

    WITH expected(id_status_siswa, kode_status) AS (
        VALUES
            (1, 'calon'),
            (1, 'mengundurkan_diri'),
            (2, 'aktif'),
            (2, 'dikeluarkan'),
            (3, 'cuti'),
            (3, 'putus_sekolah'),
            (4, 'mutasi'),
            (4, 'meninggal_dunia'),
            (5, 'lulus'),
            (6, 'keluar')
    )
    SELECT count(*) = 10
       AND count(DISTINCT kode_status) = 10
       AND NOT EXISTS (
            SELECT 1
            FROM expected e
            FULL JOIN public.status_siswa s USING (kode_status)
            WHERE e.kode_status IS NULL
               OR s.kode_status IS NULL
               OR e.id_status_siswa IS DISTINCT FROM s.id_status_siswa
       )
    INTO v_before_mapping
    FROM public.status_siswa;

    WITH expected(id_status_siswa, kode_status) AS (
        VALUES
            (1, 'calon'),
            (2, 'aktif'),
            (3, 'cuti'),
            (4, 'mutasi'),
            (5, 'lulus'),
            (6, 'keluar'),
            (7, 'mengundurkan_diri'),
            (8, 'dikeluarkan'),
            (9, 'putus_sekolah'),
            (10, 'meninggal_dunia')
    )
    SELECT count(*) = 10
       AND count(DISTINCT kode_status) = 10
       AND NOT EXISTS (
            SELECT 1
            FROM expected e
            FULL JOIN public.status_siswa s USING (kode_status)
            WHERE e.kode_status IS NULL
               OR s.kode_status IS NULL
               OR e.id_status_siswa IS DISTINCT FROM s.id_status_siswa
       )
    INTO v_after_mapping
    FROM public.status_siswa;

    SELECT count(*),
           count(*) FILTER (WHERE id_status_siswa = 2),
           count(*) FILTER (WHERE id_status_siswa = 4)
    INTO v_student_count, v_students_active, v_students_transfer
    FROM public.siswa;

    SELECT count(*)
    INTO v_approved_transfer_count
    FROM public.siswa s
    JOIN public.mutasi_siswa m ON m.id_siswa = s.id_siswa
    WHERE s.id_status_siswa = 4
      AND m.jenis = 'pindah_keluar'
      AND m.status = 'disetujui';

    IF v_student_count <> 9
       OR v_students_active <> 8
       OR v_students_transfer <> 1
       OR v_approved_transfer_count <> 1 THEN
        RAISE EXCEPTION 'Data siswa/mutasi berbeda dari preflight yang disetujui; perubahan dibatalkan';
    END IF;

    IF v_before_mapping THEN
        UPDATE public.status_siswa
        SET id_status_siswa = CASE kode_status
            WHEN 'mengundurkan_diri' THEN 7
            WHEN 'dikeluarkan' THEN 8
            WHEN 'putus_sekolah' THEN 9
            WHEN 'meninggal_dunia' THEN 10
        END
        WHERE kode_status IN ('mengundurkan_diri', 'dikeluarkan', 'putus_sekolah', 'meninggal_dunia');

        GET DIAGNOSTICS v_updated_rows = ROW_COUNT;
        IF v_updated_rows <> 4 THEN
            RAISE EXCEPTION 'Jumlah status yang dipindahkan tidak sesuai: %', v_updated_rows;
        END IF;
    ELSIF NOT v_after_mapping THEN
        RAISE EXCEPTION 'Pemetaan status bukan keadaan sebelum atau sesudah yang disetujui';
    END IF;

    WITH expected(id_status_siswa, kode_status) AS (
        VALUES
            (1, 'calon'),
            (2, 'aktif'),
            (3, 'cuti'),
            (4, 'mutasi'),
            (5, 'lulus'),
            (6, 'keluar'),
            (7, 'mengundurkan_diri'),
            (8, 'dikeluarkan'),
            (9, 'putus_sekolah'),
            (10, 'meninggal_dunia')
    )
    SELECT count(*) = 10
       AND count(DISTINCT kode_status) = 10
       AND NOT EXISTS (
            SELECT 1
            FROM expected e
            FULL JOIN public.status_siswa s USING (kode_status)
            WHERE e.kode_status IS NULL
               OR s.kode_status IS NULL
               OR e.id_status_siswa IS DISTINCT FROM s.id_status_siswa
       )
    INTO v_after_mapping
    FROM public.status_siswa;

    IF NOT v_after_mapping THEN
        RAISE EXCEPTION 'Verifikasi pascaperbaikan status_siswa gagal';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM public.status_siswa
        GROUP BY id_status_siswa
        HAVING count(*) > 1
    ) THEN
        RAISE EXCEPTION 'ID status_siswa masih duplikat setelah pemetaan';
    END IF;

    IF (
        SELECT count(*)
        FROM public.siswa s
        JOIN public.status_siswa st USING (id_status_siswa)
    ) <> 9 THEN
        RAISE EXCEPTION 'Join siswa/status_siswa tidak menghasilkan 9 baris setelah pemetaan';
    END IF;

    RAISE NOTICE 'Status siswa diverifikasi; join siswa/status_siswa=9';
END
$fase1$;
