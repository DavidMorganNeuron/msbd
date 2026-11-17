-- Local development only: create/login the app roles against the production-like local database.
-- Run manually as postgres, never as part of Laravel migrations.
-- Guard: this script must only touch the local msbd_tubes_kel7 dev database.

DO $$
BEGIN
    IF current_database() <> 'msbd_tubes_kel7' THEN
        RAISE EXCEPTION 'Script ini hanya untuk database msbd_tubes_kel7; database saat ini: %', current_database();
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_owner') THEN
        CREATE ROLE app_owner LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '1';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_login') THEN
        CREATE ROLE app_login LOGIN NOINHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '1';
    ELSE
        ALTER ROLE app_login WITH LOGIN NOINHERIT NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '1';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'role_siswa') THEN
        CREATE ROLE role_siswa NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;
    END IF;
    ALTER ROLE role_siswa WITH LOGIN PASSWORD '1' NOSUPERUSER NOCREATEDB NOCREATEROLE;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'role_tu') THEN
        CREATE ROLE role_tu NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;
    END IF;
    ALTER ROLE role_tu WITH LOGIN PASSWORD '1' NOSUPERUSER NOCREATEDB NOCREATEROLE;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'role_kepsek') THEN
        CREATE ROLE role_kepsek NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;
    END IF;
    ALTER ROLE role_kepsek WITH LOGIN PASSWORD '1' NOSUPERUSER NOCREATEDB NOCREATEROLE;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'role_wali_kelas') THEN
        CREATE ROLE role_wali_kelas NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE;
    END IF;
    ALTER ROLE role_wali_kelas WITH LOGIN PASSWORD '1' NOSUPERUSER NOCREATEDB NOCREATEROLE;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_owner') THEN
        CREATE ROLE app_owner LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '1';
    END IF;
    ALTER ROLE app_owner WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '1';
END $$;

GRANT role_siswa, role_tu, role_kepsek, role_wali_kelas TO app_login WITH INHERIT FALSE, SET TRUE;
GRANT CONNECT ON DATABASE msbd_tubes_kel7 TO app_login, app_owner, role_siswa, role_tu, role_kepsek, role_wali_kelas;
GRANT ALL PRIVILEGES ON DATABASE msbd_tubes_kel7 TO app_owner;
GRANT CONNECT ON DATABASE msbd_tubes_kel7 TO app_login;

-- Optional: keep these roles in the expected dev posture for local verification.
ALTER ROLE app_login SET search_path = pg_catalog, public, pg_temp;
ALTER ROLE app_owner SET search_path = pg_catalog, public, pg_temp;
ALTER ROLE role_siswa SET search_path = pg_catalog, public, pg_temp;
ALTER ROLE role_tu SET search_path = pg_catalog, public, pg_temp;
ALTER ROLE role_kepsek SET search_path = pg_catalog, public, pg_temp;
ALTER ROLE role_wali_kelas SET search_path = pg_catalog, public, pg_temp;
