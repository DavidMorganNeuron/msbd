-- Hardening and application defaults.

REVOKE CREATE ON SCHEMA public FROM PUBLIC;
ALTER ROLE app_login SET statement_timeout = '5s';
ALTER ROLE app_login SET idle_in_transaction_session_timeout = '30s';
ALTER ROLE app_login SET search_path = pg_catalog, public, pg_temp;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    REVOKE SELECT ON TABLES FROM role_siswa, role_tu, role_kepsek, role_wali_kelas;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    REVOKE INSERT, UPDATE ON TABLES FROM role_wali_kelas;
