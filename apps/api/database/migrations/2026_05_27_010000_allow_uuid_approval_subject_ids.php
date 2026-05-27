<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE approval_instances ALTER COLUMN subject_id TYPE varchar(255) USING subject_id::text');
        DB::statement('ALTER TABLE approval_tasks ALTER COLUMN subject_id TYPE varchar(255) USING subject_id::text');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $uuidSubjectCount = (int) DB::scalar(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM approval_tasks WHERE subject_id !~ '^[0-9]+$')
                + (SELECT COUNT(*) FROM approval_instances WHERE subject_id !~ '^[0-9]+$')
        SQL);

        if ($uuidSubjectCount > 0) {
            throw new RuntimeException(
                'Cannot roll back approval subject_id columns to bigint while UUID subject IDs exist.',
            );
        }

        DB::statement('ALTER TABLE approval_tasks ALTER COLUMN subject_id TYPE bigint USING subject_id::bigint');
        DB::statement('ALTER TABLE approval_instances ALTER COLUMN subject_id TYPE bigint USING subject_id::bigint');
    }
};
