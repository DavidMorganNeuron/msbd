WITH object_counts AS (
    SELECT 'Tabel' AS objek, count(*)::bigint AS jumlah
    FROM pg_catalog.pg_class AS c
    JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public'
      AND c.relkind IN ('r', 'p')

    UNION ALL
    SELECT 'Primary key', count(*)::bigint
    FROM pg_catalog.pg_constraint AS c
    JOIN pg_catalog.pg_namespace AS n ON n.oid = c.connamespace
    WHERE n.nspname = 'public'
      AND c.contype = 'p'

    UNION ALL
    SELECT 'Foreign key', count(*)::bigint
    FROM pg_catalog.pg_constraint AS c
    JOIN pg_catalog.pg_namespace AS n ON n.oid = c.connamespace
    WHERE n.nspname = 'public'
      AND c.contype = 'f'

    UNION ALL
    SELECT 'View', count(*)::bigint
    FROM pg_catalog.pg_class AS c
    JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public'
      AND c.relkind = 'v'

    UNION ALL
    SELECT 'Stored function non-trigger', count(*)::bigint
    FROM pg_catalog.pg_proc AS p
    JOIN pg_catalog.pg_namespace AS n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public'
      AND p.prokind = 'f'
      AND p.prorettype <> 'trigger'::regtype
      AND NOT EXISTS (
          SELECT 1
          FROM pg_catalog.pg_depend AS d
          WHERE d.classid = 'pg_catalog.pg_proc'::regclass
            AND d.objid = p.oid
            AND d.deptype = 'e'
      )

    UNION ALL
    SELECT 'Trigger function', count(*)::bigint
    FROM pg_catalog.pg_proc AS p
    JOIN pg_catalog.pg_namespace AS n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public'
      AND p.prorettype = 'trigger'::regtype

    UNION ALL
    SELECT 'Trigger non-internal', count(*)::bigint
    FROM pg_catalog.pg_trigger AS t
    JOIN pg_catalog.pg_class AS c ON c.oid = t.tgrelid
    JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public'
      AND NOT t.tgisinternal

    UNION ALL
    SELECT 'Procedure', count(*)::bigint
    FROM pg_catalog.pg_proc AS p
    JOIN pg_catalog.pg_namespace AS n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public'
      AND p.prokind = 'p'

    UNION ALL
    SELECT 'Role cluster', count(*)::bigint
    FROM pg_catalog.pg_roles

    UNION ALL
    SELECT 'Role Fase 1', count(*)::bigint
    FROM pg_catalog.pg_roles
    WHERE rolname = ANY (ARRAY[
        'app_owner',
        'app_login',
        'role_siswa',
        'role_tu',
        'role_kepsek',
        'role_wali_kelas'
    ])

    UNION ALL
    SELECT 'GRANT ACL privilege entries', (
        (SELECT count(*) FROM pg_catalog.pg_namespace AS n
         CROSS JOIN LATERAL pg_catalog.aclexplode(n.nspacl)
         WHERE n.nspname = 'public')
        +
        (SELECT count(*) FROM pg_catalog.pg_class AS c
         JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
         CROSS JOIN LATERAL pg_catalog.aclexplode(c.relacl)
         WHERE n.nspname = 'public')
        +
        (SELECT count(*) FROM pg_catalog.pg_proc AS p
         JOIN pg_catalog.pg_namespace AS n ON n.oid = p.pronamespace
         CROSS JOIN LATERAL pg_catalog.aclexplode(p.proacl)
         WHERE n.nspname = 'public')
        +
        (SELECT count(*) FROM pg_catalog.pg_database AS d
         CROSS JOIN LATERAL pg_catalog.aclexplode(d.datacl)
         WHERE d.datname = current_database())
    )::bigint

    UNION ALL
    SELECT 'UNIQUE constraint', count(*)::bigint
    FROM pg_catalog.pg_constraint AS c
    JOIN pg_catalog.pg_namespace AS n ON n.oid = c.connamespace
    WHERE n.nspname = 'public'
      AND c.contype = 'u'

    UNION ALL
    SELECT 'CHECK constraint', count(*)::bigint
    FROM pg_catalog.pg_constraint AS c
    JOIN pg_catalog.pg_namespace AS n ON n.oid = c.connamespace
    WHERE n.nspname = 'public'
      AND c.contype = 'c'
)
SELECT objek, jumlah
FROM object_counts
ORDER BY CASE objek
    WHEN 'Tabel' THEN 1
    WHEN 'Primary key' THEN 2
    WHEN 'Foreign key' THEN 3
    WHEN 'View' THEN 4
    WHEN 'Stored function non-trigger' THEN 5
    WHEN 'Trigger function' THEN 6
    WHEN 'Trigger non-internal' THEN 7
    WHEN 'Procedure' THEN 8
    WHEN 'Role cluster' THEN 9
    WHEN 'Role Fase 1' THEN 10
    WHEN 'GRANT ACL privilege entries' THEN 11
    WHEN 'UNIQUE constraint' THEN 12
    WHEN 'CHECK constraint' THEN 13
    ELSE 99
END;
