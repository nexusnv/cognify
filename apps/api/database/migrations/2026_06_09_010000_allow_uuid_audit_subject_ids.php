<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE audit_events ALTER COLUMN subject_id TYPE varchar(255) USING subject_id::text'),
            'mysql' => DB::statement('ALTER TABLE audit_events MODIFY subject_id varchar(255) NOT NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $nonNumericSubjectIds = (int) DB::selectOne("SELECT COUNT(*) AS aggregate FROM audit_events WHERE subject_id !~ '^[0-9]+$'")->aggregate;

            if ($nonNumericSubjectIds > 0) {
                throw new RuntimeException('Cannot roll back audit_events.subject_id to bigint while UUID subject IDs exist.');
            }

            DB::statement('ALTER TABLE audit_events ALTER COLUMN subject_id TYPE bigint USING subject_id::bigint');

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE audit_events MODIFY subject_id bigint unsigned NOT NULL');
        }
    }
};
