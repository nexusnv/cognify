<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->after('id');
            $table->string('action')->nullable()->after('event_type');
            $table->string('subject_display')->nullable()->after('subject_id');
            $table->json('before')->nullable()->after('metadata');
            $table->json('after')->nullable()->after('before');
            $table->string('ip_address', 45)->nullable()->after('after');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('request_id')->nullable()->after('user_agent');

            $table->unique('event_id');
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
            $table->index('request_id');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'UPDATE audit_events SET event_id = gen_random_uuid(), action = event_type WHERE event_id IS NULL',
            );

            return;
        }

        if ($driver === 'mysql') {
            DB::statement(
                'UPDATE audit_events SET event_id = UUID(), action = event_type WHERE event_id IS NULL',
            );

            return;
        }

        DB::table('audit_events')
            ->whereNull('event_id')
            ->orderBy('id')
            ->chunkById(500, function ($events): void {
                DB::transaction(function () use ($events): void {
                    foreach ($events as $event) {
                        DB::table('audit_events')
                            ->where('id', $event->id)
                            ->whereNull('event_id')
                            ->update([
                                'event_id' => (string) Str::uuid(),
                                'action' => $event->event_type,
                            ]);
                    }
                });
            });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropUnique(['event_id']);
            $table->dropIndex(['tenant_id', 'action']);
            $table->dropIndex(['tenant_id', 'occurred_at']);
            $table->dropIndex(['tenant_id', 'subject_type', 'subject_id']);
            $table->dropIndex(['request_id']);
            $table->dropColumn([
                'event_id',
                'action',
                'subject_display',
                'before',
                'after',
                'ip_address',
                'user_agent',
                'request_id',
            ]);
        });
    }
};
