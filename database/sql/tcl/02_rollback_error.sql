BEGIN;
UPDATE public.pengguna
SET username = 'fase1_demo_siswa_tx_' || txid_current()
WHERE username = 'fase1_demo_siswa';
SAVEPOINT p1;
UPDATE public.siswa
SET nama_lengkap = 'Perubahan yang dibatalkan'
WHERE nis = 'FASE1-DEMO-001';
ROLLBACK TO SAVEPOINT p1;
ROLLBACK;
