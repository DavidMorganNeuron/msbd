-- Hardening and application defaults.

REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT CREATE ON SCHEMA public TO app_login;
ALTER ROLE app_login NOINHERIT;
GRANT role_siswa, role_tu, role_kepsek, role_wali_kelas TO app_login WITH INHERIT FALSE, SET TRUE;
ALTER ROLE app_login RESET statement_timeout;
ALTER ROLE app_login RESET idle_in_transaction_session_timeout;
ALTER ROLE app_login RESET search_path;

DO $$
BEGIN
    EXECUTE format(
        'ALTER ROLE app_login IN DATABASE %I SET statement_timeout = %L',
        current_database(),
        '30s'
    );
    EXECUTE format(
        'ALTER ROLE app_login IN DATABASE %I SET idle_in_transaction_session_timeout = %L',
        current_database(),
        '30s'
    );
    EXECUTE format(
        'ALTER ROLE app_login IN DATABASE %I SET search_path = pg_catalog, public, pg_temp',
        current_database()
    );
END $$;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    REVOKE SELECT ON TABLES FROM role_siswa, role_tu, role_kepsek, role_wali_kelas;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    REVOKE INSERT, UPDATE ON TABLES FROM role_wali_kelas;
