-- Single-session lock smoke test; a deadlock requires two concurrent sessions.
BEGIN;
SELECT id_pengguna
FROM public.pengguna
WHERE username = 'fase1_demo_siswa'
FOR UPDATE;
SELECT id_pengguna
FROM public.pengguna
WHERE username = 'fase1_demo_siswa'
FOR UPDATE SKIP LOCKED;
ROLLBACK;
