<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignIdFor(User::class, 'approval_submitted_by_user_id')->nullable()->after('ready_for_review_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approval_submitted_at')->nullable()->after('approval_submitted_by_user_id');
            $table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->after('approval_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->foreignIdFor(User::class, 'rejected_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            $table->text('rejected_reason')->nullable()->after('rejected_at');
            $table->foreignIdFor(User::class, 'changes_requested_by_user_id')->nullable()->after('rejected_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('changes_requested_at')->nullable()->after('changes_requested_by_user_id');
            $table->text('changes_requested_reason')->nullable()->after('changes_requested_at');
            $table->json('changes_requested_fields')->nullable()->after('changes_requested_reason');

            $table->index(['tenant_id', 'status', 'approval_submitted_at'], 'purchase_orders_tenant_status_submitted_idx');
            $table->index(['tenant_id', 'approval_instance_id'], 'purchase_orders_tenant_approval_instance_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_orders_tenant_status_submitted_idx');
            $table->dropIndex('purchase_orders_tenant_approval_instance_idx');
            $table->dropConstrainedForeignId('approval_submitted_by_user_id');
            $table->dropColumn('approval_submitted_at');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejected_at', 'rejected_reason']);
            $table->dropConstrainedForeignId('changes_requested_by_user_id');
            $table->dropColumn(['changes_requested_at', 'changes_requested_reason', 'changes_requested_fields']);
        });
    }
};
