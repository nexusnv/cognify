<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE attachments ALTER COLUMN attachable_id TYPE uuid USING attachable_id::uuid'),
            'mysql' => DB::statement('ALTER TABLE attachments MODIFY attachable_id char(36) NOT NULL'),
            default => Schema::table('attachments', static function (Blueprint $table): void {
                $table->uuid('attachable_id')->change();
            }),
        };
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $nonNumericAttachableIds = (int) DB::selectOne("SELECT COUNT(*) AS aggregate FROM attachments WHERE attachable_id !~ '^[0-9a-fA-F-]{36}$'")->aggregate;

            if ($nonNumericAttachableIds > 0) {
                throw new RuntimeException('Cannot roll back attachments.attachable_id to bigint while UUID attachable IDs exist.');
            }

            DB::statement('ALTER TABLE attachments ALTER COLUMN attachable_id TYPE bigint USING attachable_id::bigint');

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            $nonNumericAttachableIds = (int) DB::selectOne("SELECT COUNT(*) AS aggregate FROM attachments WHERE attachable_id NOT REGEXP '^[0-9a-fA-F-]{36}$'")->aggregate;

            if ($nonNumericAttachableIds > 0) {
                throw new RuntimeException('Cannot roll back attachments.attachable_id to bigint while UUID attachable IDs exist.');
            }

            DB::statement('ALTER TABLE attachments MODIFY attachable_id bigint unsigned NOT NULL');

            return;
        }

        Schema::table('attachments', static function (Blueprint $table): void {
            $table->unsignedBigInteger('attachable_id')->change();
        });
    }
};
