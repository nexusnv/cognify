<?php

use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_award_recommendations', function (Blueprint $table): void {
            $table->foreignIdFor(ApprovalInstance::class, 'approval_instance_id')->nullable()->after('status')->constrained('approval_instances')->nullOnDelete();
            $table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->after('withdrawn_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->foreignIdFor(User::class, 'rejected_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            $table->text('decision_reason')->nullable()->after('rejected_at');
            $table->foreignIdFor(User::class, 'changes_requested_by_user_id')->nullable()->after('decision_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('changes_requested_at')->nullable()->after('changes_requested_by_user_id');
            $table->text('changes_requested_reason')->nullable()->after('changes_requested_at');
            $table->json('changes_requested_fields')->nullable()->after('changes_requested_reason');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_award_recommendations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approval_instance_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejected_at', 'decision_reason']);
            $table->dropConstrainedForeignId('changes_requested_by_user_id');
            $table->dropColumn(['changes_requested_at', 'changes_requested_reason', 'changes_requested_fields']);
        });
    }
};
