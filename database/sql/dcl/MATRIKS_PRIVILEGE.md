# Matriks privilege

`MATRIKS_PRIVILEGE.csv` is the canonical, exhaustive matrix for the six PostgreSQL roles. It contains
one row for every role/object pair: 72 tables, 13 views, seven stored functions, five trigger
functions, four procedures, the public and `app_owner` schemas, and the sequence set. Empty privilege cells mean
that the role receives no explicit grant. The `notes` column records the intended scope or an open
review item.

Regenerate [02_privileges.sql](./02_privileges.sql) after editing the CSV:

```powershell
.\database\sql\dcl\hasilkan_privilege.ps1
```

The generator validates the CSV header, allowed role and object types, duplicate pairs, and six-role
coverage before replacing the SQL. The SQL revokes prior grants from `PUBLIC` and the six roles,
then emits only the matrix grants. It is designed to be idempotent.

## Permission design

| Role | Explicit access |
| --- | --- |
| `role_siswa` | Read the student, role, academic score, and confirmation relations; call the request-insert function in the private `app_owner` schema and insert/read/update request details, including the generated detail ID needed by `INSERT RETURNING`. |
| `role_tu` | Maintain reference data; verify and approve PPDB applications; edit student profiles; manage academic years, semesters, class structure, subjects, assessment components, predicates, schedules, and student transfer requests; assign homeroom teachers; create student accounts without reading password hashes. Writes remain column-limited where supported. |
| `role_kepsek` | Read shared relations and the audit log; create user accounts; decide eligible transfer requests, update the affected student and class-membership status, and notify the requesting TU account. |
| `role_wali_kelas` | Read shared student/academic relations and reviewer eligibility fields; write academic rows; review and update change requests/details; execute `sp_setujui_pengajuan`. |
| `app_login` | Inherits the union of the four business-role grants, serves and saves public PPDB applications (including limited reference synchronization), manages Laravel's session/cache/queue/token tables and migration ledger, reads `pengguna` for authentication, updates password/login metadata, and appends `log_login`. |
| `app_owner` | Own the private routine schema, request-insert function, approval procedure, and audit trigger function; access only their support tables, audit insert, and sequences. |

`app_owner` is separate from the business-role matrix and owns security-definer routines. The local development role script may enable login for DDL and verification; it must not be used as an end-user application role.

`pengguna.password_hash` is not readable by business roles; `app_login` can read it for Laravel
authentication. Business roles are `NOLOGIN`; `app_login` is `LOGIN INHERIT` and is a non-switchable
member of all four business roles so one Laravel database connection can run each authenticated
workflow. This deliberately gives every request the union of those database privileges.

## TODO-REVIEW before switching the application to these roles

1. PostgreSQL table grants do not enforce per-student, per-class, or per-row ownership. The CSV
   explicitly marks those data paths as application-enforced; no row-level security policy exists.
2. The single `app_login` connection has the union of four business-role privileges. Without RLS or
   per-request role switching, database authorization is not isolated by the logged-in Laravel role.
   The application authorization layer remains the sole row-level boundary.
3. Keep model-level audit writes behind `AuditCatat`: PostgreSQL audit remains trigger-owned, while
   SQLite retains the helper fallback. Do not write to `log_audit` directly from business roles.
4. The PPDB approval flow now has column-limited writes for `role_tu`; other account-management
   writes and non-approval procedures still need an explicit caller/column audit.
5. Student password changes use the identity loaded by `EnsureRole` before the PostgreSQL role
   switch, so business roles do not need `SELECT` on `pengguna.password_hash`. Continue end-to-end
   authorization testing for role-specific notifications, reports, and views.

The clone verifier confirms the grants in the CSV, but a green grant verifier is not proof of
row-level isolation or full application compatibility. See [FASE1_LAPORAN.md](../FASE1_LAPORAN.md)
for measured execution results and remaining findings.
