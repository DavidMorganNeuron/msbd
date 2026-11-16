-- Bootstrap schema for the school database.
-- This file is intentionally idempotent and safe to rerun.

CREATE TABLE IF NOT EXISTS public.role (
    id_role BIGSERIAL PRIMARY KEY,
    nama_role VARCHAR(50) NOT NULL UNIQUE,
    keterangan TEXT,
    dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.hak_akses (
    id_hak_akses BIGSERIAL PRIMARY KEY,
    nama_hak_akses VARCHAR(100) NOT NULL UNIQUE,
    deskripsi TEXT,
    dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.role_hak_akses (
    id_role_hak_akses BIGSERIAL PRIMARY KEY,
    id_role BIGINT NOT NULL REFERENCES public.role(id_role) ON DELETE CASCADE,
    id_hak_akses BIGINT NOT NULL REFERENCES public.hak_akses(id_hak_akses) ON DELETE CASCADE,
    UNIQUE (id_role, id_hak_akses)
);

CREATE TABLE IF NOT EXISTS public.pengguna (
    id_pengguna BIGSERIAL PRIMARY KEY,
    id_role BIGINT NOT NULL REFERENCES public.role(id_role),
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_aktif BOOLEAN NOT NULL DEFAULT TRUE,
    terakhir_login TIMESTAMPTZ,
    dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diubah_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.siswa (
    id_siswa BIGSERIAL PRIMARY KEY,
    id_pengguna BIGINT REFERENCES public.pengguna(id_pengguna) ON DELETE SET NULL,
    nis VARCHAR(30) NOT NULL UNIQUE,
    nisn VARCHAR(30),
    nik VARCHAR(20),
    nama_lengkap VARCHAR(150) NOT NULL,
    jenis_kelamin VARCHAR(20),
    tempat_lahir VARCHAR(100),
    tanggal_lahir DATE,
    email VARCHAR(150),
    no_telepon VARCHAR(30),
    status_siswa VARCHAR(30) NOT NULL DEFAULT 'aktif',
    dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diubah_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.periode_update (
    id_periode_update BIGSERIAL PRIMARY KEY,
    id_tahun_ajaran BIGINT,
    nama_periode VARCHAR(150) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'aktif', 'ditutup')),
    dibuat_oleh BIGINT REFERENCES public.pengguna(id_pengguna),
    CHECK (tanggal_selesai >= tanggal_mulai)
);

CREATE TABLE IF NOT EXISTS public.konfirmasi_data (
    id_konfirmasi BIGSERIAL PRIMARY KEY,
    id_periode_update BIGINT NOT NULL REFERENCES public.periode_update(id_periode_update) ON DELETE RESTRICT,
    id_siswa BIGINT NOT NULL REFERENCES public.siswa(id_siswa) ON DELETE RESTRICT,
    status_konfirmasi VARCHAR(20) NOT NULL DEFAULT 'belum' CHECK (status_konfirmasi IN ('belum', 'sudah', 'ditolak')),
    catatan TEXT,
    dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_periode_update, id_siswa)
);

CREATE TABLE IF NOT EXISTS public.pengajuan_perubahan (
    id_pengajuan BIGSERIAL PRIMARY KEY,
    id_siswa BIGINT NOT NULL REFERENCES public.siswa(id_siswa) ON DELETE RESTRICT,
    id_pemohon BIGINT NOT NULL REFERENCES public.pengguna(id_pengguna) ON DELETE RESTRICT,
    status_pengajuan VARCHAR(30) NOT NULL DEFAULT 'menunggu' CHECK (status_pengajuan IN ('menunggu', 'disetujui', 'ditolak')),
    catatan_pemohon TEXT,
    catatan_pemeriksa TEXT,
    dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diubah_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.detail_pengajuan (
    id_detail_pengajuan BIGSERIAL PRIMARY KEY,
    id_pengajuan BIGINT NOT NULL REFERENCES public.pengajuan_perubahan(id_pengajuan) ON DELETE CASCADE,
    nama_field VARCHAR(100) NOT NULL,
    nilai_lama JSONB,
    nilai_baru JSONB,
    dibuat_pada TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.rapor (
    id_rapor BIGSERIAL PRIMARY KEY,
    id_anggota BIGINT NOT NULL REFERENCES public.siswa(id_siswa) ON DELETE RESTRICT,
    id_semester BIGINT,
    id_penyusun BIGINT NOT NULL REFERENCES public.pengguna(id_pengguna) ON DELETE RESTRICT,
    rata_rata NUMERIC(5,2),
    status_rapor VARCHAR(30) NOT NULL DEFAULT 'draft' CHECK (status_rapor IN ('draft', 'final')),
    tgl_dibuat TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.nilai_siswa (
    id_nilai BIGSERIAL PRIMARY KEY,
    id_siswa BIGINT NOT NULL REFERENCES public.siswa(id_siswa) ON DELETE RESTRICT,
    id_komponen_penilaian BIGINT,
    nilai NUMERIC(5,2),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (nilai IS NULL OR (nilai >= 0 AND nilai <= 100))
);

CREATE TABLE IF NOT EXISTS public.detail_absensi (
    id_detail_absensi BIGSERIAL PRIMARY KEY,
    id_siswa BIGINT NOT NULL REFERENCES public.siswa(id_siswa) ON DELETE RESTRICT,
    id_sesi_absensi BIGINT,
    status_kehadiran VARCHAR(30) NOT NULL DEFAULT 'hadir' CHECK (status_kehadiran IN ('hadir', 'izin', 'sakit', 'alpa')),
    keterangan TEXT,
    catatan TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.log_audit (
    id_log_audit BIGSERIAL PRIMARY KEY,
    nama_tabel VARCHAR(100) NOT NULL,
    operasi VARCHAR(20) NOT NULL CHECK (operasi IN ('INSERT', 'UPDATE', 'DELETE')),
    id_data VARCHAR(100),
    nilai_lama JSONB,
    nilai_baru JSONB,
    id_pengguna_pengubah BIGINT,
    username_pengubah VARCHAR(100),
    waktu TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.log_login (
    id_log_login BIGSERIAL PRIMARY KEY,
    id_pengguna BIGINT REFERENCES public.pengguna(id_pengguna) ON DELETE SET NULL,
    username VARCHAR(100),
    peristiwa VARCHAR(50) NOT NULL DEFAULT 'login',
    status VARCHAR(20) NOT NULL DEFAULT 'berhasil' CHECK (status IN ('berhasil', 'gagal')),
    ip_address VARCHAR(64),
    waktu TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
