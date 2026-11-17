<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
GRANT INSERT ON TABLE public.log_audit TO app_owner;
GRANT USAGE ON SEQUENCE public.log_audit_id_log_seq TO app_owner;
SQL);
    }

    /**
     * Keep the grants because the security-definer audit trigger depends on them.
     */
    public function down(): void {}
};
