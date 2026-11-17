# FASE 2 - Progress Log

## 2026-11-17

1. Confirmed the application and test stack are healthy for the local Laravel project and target the PostgreSQL database `msbd_tubes_kel7` in local development.
2. Fixed the demo-role seeder regression so local demo accounts are created idempotently and the `wali_kelas` role is included.
3. Updated local environment defaults to match the required dev profile: `DB_USERNAME=app_login`, `DB_PASSWORD=1`, `DEMO_ROLE_PASSWORD=test123`, and `APP_DEBUG=false`.
4. Added the manual PostgreSQL dev role script at `database/sql/dcl/04_login_roles_dev.sql` to create and configure the required login roles without adding them to Laravel migrations.
5. Verification status: project tests remain green (`php artisan test --compact`), and the remaining PostgreSQL role setup is intentionally kept as a manual `psql` step because it must be executed as the `postgres` role with direct database access.
6. Final hardening: confirmed the active PostgreSQL runtime is using `listen_addresses = 'localhost'` and `scram-sha-256`, and the active `pg_hba.conf` only permits localhost TCP access (`127.0.0.1/32` and `::1/128`). A Windows service restart was attempted, but the current session could not stop the service; the effective running instance already reflects the intended localhost-only state.
7. Completed the owner-authorized reset of `msbd_tubes_kel7`. The first run exposed a missing `app_owner` INSERT/sequence grant used by the security-definer audit trigger; added a forward migration and reran `php artisan migrate:fresh --seed` successfully. The schema dump now contains all 27 migration rows.
8. Reapplied PostgreSQL DCL in order. The first hardened `php artisan migrate` check exposed missing `app_login` access to Laravel's `migrations` table/sequence; added those grants to the source privilege matrix, regenerated `02_privileges.sql`, and verified that `php artisan migrate` now reports `Nothing to migrate`.
9. Final verification: 72 tables, 13 views, 29 enabled triggers, 4 active demo accounts, 1 linked demo student, and 251 audit rows. PostgreSQL verifier: 240 PASS/0 FAIL. Four business-role logins were tested; all are non-superusers. `app_login` is also `NOSUPERUSER`, and `app_owner` is `NOLOGIN`.
10. The local verification for the active `msbd_tubes_kel7` database was performed without creating a disposable clone. The role hardening and privilege checks were validated directly against the existing PostgreSQL login profile, with `app_login` remaining `NOSUPERUSER` and the ownership role kept separate for DDL verification.
