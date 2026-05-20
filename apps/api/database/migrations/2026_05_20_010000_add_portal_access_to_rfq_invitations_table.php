<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_invitations', function (Blueprint $table): void {
            $table->string('portal_token_hash', 64)->nullable()->unique()->after('metadata');
            $table->timestamp('portal_token_created_at')->nullable()->after('portal_token_hash');
            $table->timestamp('portal_token_expires_at')->nullable()->after('portal_token_created_at');
            $table->timestamp('portal_last_viewed_at')->nullable()->after('portal_token_expires_at');
            $table->unsignedInteger('portal_view_count')->default(0)->after('portal_last_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_invitations', function (Blueprint $table): void {
            $table->dropUnique(['portal_token_hash']);
            $table->dropColumn([
                'portal_token_hash',
                'portal_token_created_at',
                'portal_token_expires_at',
                'portal_last_viewed_at',
                'portal_view_count',
            ]);
        });
    }
};
