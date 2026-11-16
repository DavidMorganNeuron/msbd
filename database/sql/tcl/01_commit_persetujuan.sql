-- Commit approval of the reserved Fase 1 demo submission.
BEGIN;
DO $demo$
DECLARE
    v_id_pengajuan BIGINT;
    v_id_pemeriksa BIGINT;
BEGIN
    SELECT pp.id_pengajuan
      INTO v_id_pengajuan
      FROM public.pengajuan_perubahan AS pp
      JOIN public.siswa AS s ON s.id_siswa = pp.id_siswa
     WHERE s.nis = 'FASE1-DEMO-001'
       AND pp.alasan = 'FASE1_DEMO'
       AND pp.status_pengajuan = 'menunggu'
     ORDER BY pp.id_pengajuan
     LIMIT 1
     FOR UPDATE OF pp;

    SELECT id_pengguna
      INTO v_id_pemeriksa
      FROM public.pengguna
     WHERE username = 'fase1_demo_tu';

    IF v_id_pengajuan IS NOT NULL AND v_id_pemeriksa IS NOT NULL THEN
        CALL public.sp_setujui_pengajuan(v_id_pengajuan, v_id_pemeriksa, 'Persetujuan demo Fase 1');
    END IF;
END
$demo$;
COMMIT;
