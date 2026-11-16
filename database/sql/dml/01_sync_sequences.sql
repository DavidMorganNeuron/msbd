-- Synchronize every public serial/identity sequence after all primary keys are installed.
DO $fase1$
DECLARE
    v record;
    v_primary_key_count integer;
    v_table_count integer;
    v_sequence_count integer := 0;
    v_max_id bigint;
    v_sequence_start bigint;
BEGIN
    IF current_database() <> 'msbd_tubes_kel7_staging' THEN
        RAISE EXCEPTION 'Sinkronisasi sequence hanya diizinkan pada msbd_tubes_kel7_staging';
    END IF;

    SELECT count(*)
    INTO v_table_count
    FROM pg_class c
    JOIN pg_namespace n ON n.oid = c.relnamespace
    WHERE n.nspname = 'public'
      AND c.relkind IN ('r', 'p');

    SELECT count(*)
    INTO v_primary_key_count
    FROM pg_constraint c
    WHERE c.contype = 'p'
      AND c.connamespace = 'public'::regnamespace;

    IF v_table_count <> 72 OR v_primary_key_count <> 72 THEN
        RAISE EXCEPTION 'Sinkronisasi ditolak: tabel public=% dan PK public=%; keduanya harus 72',
            v_table_count, v_primary_key_count;
    END IF;

    FOR v IN
        SELECT sequence_class.oid AS sequence_oid,
               table_class.relname AS table_name,
               attribute.attname AS column_name
        FROM pg_class sequence_class
        JOIN pg_namespace sequence_namespace ON sequence_namespace.oid = sequence_class.relnamespace
        JOIN pg_depend dependency
          ON dependency.classid = 'pg_class'::regclass
         AND dependency.objid = sequence_class.oid
         AND dependency.refclassid = 'pg_class'::regclass
         AND dependency.deptype IN ('a', 'i')
        JOIN pg_class table_class ON table_class.oid = dependency.refobjid
        JOIN pg_namespace table_namespace ON table_namespace.oid = table_class.relnamespace
        JOIN pg_attribute attribute
          ON attribute.attrelid = table_class.oid
         AND attribute.attnum = dependency.refobjsubid
         AND NOT attribute.attisdropped
        WHERE sequence_class.relkind = 'S'
          AND sequence_namespace.nspname = 'public'
          AND table_namespace.nspname = 'public'
        ORDER BY table_class.relname, attribute.attname
    LOOP
        EXECUTE format('SELECT max(%I) FROM public.%I', v.column_name, v.table_name)
        INTO v_max_id;

        IF v_max_id IS NULL THEN
            SELECT sequence_catalog.seqstart
            INTO v_sequence_start
            FROM pg_sequence sequence_catalog
            WHERE sequence_catalog.seqrelid = v.sequence_oid;

            PERFORM setval(v.sequence_oid::regclass, v_sequence_start, false);
        ELSE
            PERFORM setval(v.sequence_oid::regclass, v_max_id, true);
        END IF;

        v_sequence_count := v_sequence_count + 1;
    END LOOP;

    RAISE NOTICE 'Sequence public disinkronkan: %', v_sequence_count;
END
$fase1$;
