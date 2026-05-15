<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->timestamp('changes_requested_at')->nullable()->after('submitted_at');
            $table->foreignId('changes_requested_by_id')->nullable()->after('changes_requested_at')->constrained('users')->nullOnDelete();
            $table->text('change_request_reason')->nullable()->after('changes_requested_by_id');
            $table->json('change_request_fields')->nullable()->after('change_request_reason');
            $table->timestamp('withdrawn_at')->nullable()->after('change_request_fields');
            $table->foreignId('withdrawn_by_id')->nullable()->after('withdrawn_at')->constrained('users')->nullOnDelete();
            $table->text('withdrawal_reason')->nullable()->after('withdrawn_by_id');
            $table->timestamp('cancelled_at')->nullable()->after('withdrawal_reason');
            $table->foreignId('cancelled_by_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by_id');
            $table->dropConstrainedForeignId('withdrawn_by_id');
            $table->dropConstrainedForeignId('changes_requested_by_id');
            $table->dropColumn([
                'changes_requested_at',
                'change_request_reason',
                'change_request_fields',
                'withdrawn_at',
                'withdrawal_reason',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
