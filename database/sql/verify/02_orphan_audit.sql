SELECT set_config('fase1.confirmed_target', 'msbd_tubes_kel7', FALSE);

DO $$
BEGIN
    IF current_database() NOT IN ('msbd_tubes_kel7_staging', 'msbd_latihan_utama', 'msbd_tubes_kel7') THEN
        RAISE EXCEPTION 'Orphan audit ditolak pada database %', current_database();
    END IF;
    IF current_database() = 'msbd_latihan_utama'
       AND NOT EXISTS (
           SELECT 1 FROM pg_database
           WHERE datname = current_database()
             AND shobj_description(oid, 'pg_database') = 'FASE1_TRAINING_CLONE'
       ) THEN
        RAISE EXCEPTION 'Database latihan tidak memiliki marker FASE1_TRAINING_CLONE';
    END IF;
    IF current_database() = 'msbd_tubes_kel7'
       AND current_setting('fase1.confirmed_target', true) IS DISTINCT FROM current_database() THEN
        RAISE EXCEPTION 'Database utama memerlukan konfirmasi target dari runner Fase 1';
    END IF;
END $$;

CREATE TEMP TABLE fk_audit (
    constraint_name text NOT NULL,
    child_table text NOT NULL,
    child_columns text NOT NULL,
    parent_table text NOT NULL,
    parent_columns text NOT NULL,
    on_delete text NOT NULL,
    validation_status text NOT NULL,
    match_type text NOT NULL,
    orphan_rows bigint NOT NULL
);

BEGIN TRANSACTION READ ONLY;

DO $$
DECLARE
    fk record;
    child_columns text;
    parent_columns text;
    join_condition text;
    all_child_values text;
    any_child_value_null text;
    all_child_values_null text;
    orphan_condition text;
    orphan_count bigint;
    match_name text;
BEGIN
    FOR fk IN
        SELECT
            c.conname,
            c.conrelid,
            c.confrelid,
            c.conkey,
            c.confkey,
            c.confdeltype,
            c.convalidated,
            c.confmatchtype,
            child_namespace.nspname AS child_schema,
            child_relation.relname AS child_relation,
            parent_namespace.nspname AS parent_schema,
            parent_relation.relname AS parent_relation
        FROM pg_catalog.pg_constraint AS c
        JOIN pg_catalog.pg_class AS child_relation ON child_relation.oid = c.conrelid
        JOIN pg_catalog.pg_namespace AS child_namespace ON child_namespace.oid = child_relation.relnamespace
        JOIN pg_catalog.pg_class AS parent_relation ON parent_relation.oid = c.confrelid
        JOIN pg_catalog.pg_namespace AS parent_namespace ON parent_namespace.oid = parent_relation.relnamespace
        WHERE c.contype = 'f'
          AND child_namespace.nspname = 'public'
        ORDER BY child_relation.relname, c.conname
    LOOP
        SELECT
            string_agg(format('%I', child_attribute.attname), ', ' ORDER BY child_key.ordinality),
            string_agg(format('%I', parent_attribute.attname), ', ' ORDER BY child_key.ordinality),
            string_agg(
                format('parent.%I = child.%I', parent_attribute.attname, child_attribute.attname),
                ' AND ' ORDER BY child_key.ordinality
            ),
            string_agg(format('child.%I IS NOT NULL', child_attribute.attname), ' AND ' ORDER BY child_key.ordinality),
            string_agg(format('child.%I IS NULL', child_attribute.attname), ' OR ' ORDER BY child_key.ordinality),
            string_agg(format('child.%I IS NULL', child_attribute.attname), ' AND ' ORDER BY child_key.ordinality)
        INTO
            child_columns,
            parent_columns,
            join_condition,
            all_child_values,
            any_child_value_null,
            all_child_values_null
        FROM unnest(fk.conkey) WITH ORDINALITY AS child_key(attnum, ordinality)
        JOIN unnest(fk.confkey) WITH ORDINALITY AS parent_key(attnum, ordinality)
            USING (ordinality)
        JOIN pg_catalog.pg_attribute AS child_attribute
            ON child_attribute.attrelid = fk.conrelid
           AND child_attribute.attnum = child_key.attnum
           AND NOT child_attribute.attisdropped
        JOIN pg_catalog.pg_attribute AS parent_attribute
            ON parent_attribute.attrelid = fk.confrelid
           AND parent_attribute.attnum = parent_key.attnum
           AND NOT parent_attribute.attisdropped;

        match_name := CASE fk.confmatchtype
            WHEN 's' THEN 'SIMPLE'
            WHEN 'f' THEN 'FULL'
            WHEN 'p' THEN 'PARTIAL'
        END;

        IF match_name = 'PARTIAL' THEN
            RAISE EXCEPTION 'MATCH PARTIAL tidak didukung untuk audit FK %', fk.conname;
        END IF;

        orphan_condition := format(
            '(%s AND NOT EXISTS (SELECT 1 FROM %I.%I AS parent WHERE %s))',
            all_child_values,
            fk.parent_schema,
            fk.parent_relation,
            join_condition
        );
        IF match_name = 'FULL' THEN
            orphan_condition := format(
                '(%s OR ((%s) AND NOT (%s)))',
                orphan_condition,
                any_child_value_null,
                all_child_values_null
            );
        END IF;

        EXECUTE format(
            'SELECT count(*) FROM %I.%I AS child WHERE %s',
            fk.child_schema,
            fk.child_relation,
            orphan_condition
        )
        INTO orphan_count;

        INSERT INTO pg_temp.fk_audit (
            constraint_name,
            child_table,
            child_columns,
            parent_table,
            parent_columns,
            on_delete,
            validation_status,
            match_type,
            orphan_rows
        )
        VALUES (
            fk.conname,
            format('%s.%s', fk.child_schema, fk.child_relation),
            child_columns,
            format('%s.%s', fk.parent_schema, fk.parent_relation),
            parent_columns,
            CASE fk.confdeltype
                WHEN 'a' THEN 'NO ACTION'
                WHEN 'r' THEN 'RESTRICT'
                WHEN 'c' THEN 'CASCADE'
                WHEN 'n' THEN 'SET NULL'
                WHEN 'd' THEN 'SET DEFAULT'
            END,
            CASE WHEN fk.convalidated THEN 'VALID' ELSE 'NOT VALID' END,
            match_name,
            orphan_count
        );
    END LOOP;
END $$;

SELECT
    constraint_name,
    child_table,
    child_columns,
    parent_table,
    parent_columns,
    on_delete,
    validation_status,
    match_type,
    orphan_rows
FROM pg_temp.fk_audit
ORDER BY child_table, constraint_name;

ROLLBACK;
