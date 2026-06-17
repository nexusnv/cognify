<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreignId('approval_instance_id')->nullable()->index()->constrained('approval_instances')->nullOnDelete();

            $table->foreignIdFor(User::class, 'approval_submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approval_submitted_at')->nullable();

            $table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignIdFor(User::class, 'rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();

            $table->foreignIdFor(User::class, 'changes_requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changes_requested_at')->nullable();
            $table->text('changes_requested_reason')->nullable();
            $table->json('changes_requested_fields')->nullable();

            $table->boolean('stp_eligible')->default(false);
            $table->timestamp('stp_processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_instance_id');
            $table->dropConstrainedForeignId('approval_submitted_by_user_id');
            $table->dropColumn('approval_submitted_at');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejected_at', 'rejected_reason']);
            $table->dropConstrainedForeignId('changes_requested_by_user_id');
            $table->dropColumn(['changes_requested_at', 'changes_requested_reason', 'changes_requested_fields']);
            $table->dropColumn(['stp_eligible', 'stp_processed_at']);
        });
    }
};
