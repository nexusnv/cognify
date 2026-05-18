<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable()->after('change_request_fields');
            $table->foreignId('approved_by_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_by_id');
            $table->foreignId('rejected_by_id')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by_id');
            $table->foreignId('approval_instance_id')->nullable()->after('rejection_reason')->constrained('approval_instances')->nullOnDelete();

            $table->index('approval_instance_id');
            $table->index(['tenant_id', 'approval_instance_id']);
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'approval_instance_id']);
            $table->dropConstrainedForeignId('approval_instance_id');
            $table->dropConstrainedForeignId('rejected_by_id');
            $table->dropConstrainedForeignId('approved_by_id');
            $table->dropColumn([
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
