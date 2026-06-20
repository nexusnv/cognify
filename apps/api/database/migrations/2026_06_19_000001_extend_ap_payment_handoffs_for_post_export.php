<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ap_payment_handoffs', function (Blueprint $table): void {
            $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('last_export_format');
            $table->timestamp('scheduled_at')->nullable()->after('scheduled_by_user_id');
            $table->date('scheduled_for_date')->nullable()->after('scheduled_at');
            $table->string('payment_reference', 255)->nullable()->after('scheduled_for_date');

            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('payment_reference');
            $table->timestamp('paid_at')->nullable()->after('paid_by_user_id');
            $table->timestamp('remittance_advice_sent_at')->nullable()->after('paid_at');

            $table->foreignId('failed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('remittance_advice_sent_at');
            $table->timestamp('failed_at')->nullable()->after('failed_by_user_id');
            $table->string('failure_code', 50)->nullable()->after('failed_at');
            $table->text('failure_reason')->nullable()->after('failure_code');

            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('failure_reason');
            $table->timestamp('voided_at')->nullable()->after('voided_by_user_id');
            $table->text('void_reason')->nullable()->after('voided_at');

            $table->decimal('variance_amount', 20, 4)->nullable()->after('void_reason');
            $table->text('variance_reason')->nullable()->after('variance_amount');
            $table->foreignId('variance_closed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('variance_reason');
            $table->timestamp('variance_closed_at')->nullable()->after('variance_closed_by_user_id');

            $table->index(['tenant_id', 'status', 'scheduled_at'], 'aph_tenant_status_scheduled_idx');
            $table->index(['tenant_id', 'status', 'paid_at'], 'aph_tenant_status_paid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ap_payment_handoffs', function (Blueprint $table): void {
            $table->dropIndex('aph_tenant_status_scheduled_idx');
            $table->dropIndex('aph_tenant_status_paid_idx');
            $table->dropColumn([
                'scheduled_by_user_id', 'scheduled_at', 'scheduled_for_date', 'payment_reference',
                'paid_by_user_id', 'paid_at', 'remittance_advice_sent_at',
                'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason',
                'voided_by_user_id', 'voided_at', 'void_reason',
                'variance_amount', 'variance_reason', 'variance_closed_by_user_id', 'variance_closed_at',
            ]);
        });
    }
};
