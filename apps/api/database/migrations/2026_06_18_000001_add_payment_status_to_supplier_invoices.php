<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->string('payment_status', 50)->nullable()->after('stp_processed_at');
            $table->timestamp('payment_eligible_at')->nullable()->after('payment_status');
            $table->foreignId('payment_on_hold_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete()->after('payment_eligible_at');
            $table->timestamp('payment_on_hold_at')->nullable()->after('payment_on_hold_by_user_id');
            $table->text('payment_on_hold_reason')->nullable()->after('payment_on_hold_at');
            $table->foreignId('payment_hold_released_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete()->after('payment_on_hold_reason');
            $table->timestamp('payment_hold_released_at')->nullable()->after('payment_hold_released_by_user_id');
            $table->text('payment_hold_released_note')->nullable()->after('payment_hold_released_at');
            $table->index(['tenant_id', 'payment_status'], 'si_tenant_payment_status_idx');
            $table->index(['tenant_id', 'payment_status', 'due_date'], 'si_tenant_payment_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropIndex('si_tenant_payment_status_due_idx');
            $table->dropIndex('si_tenant_payment_status_idx');
            $table->dropColumn(['payment_status', 'payment_eligible_at', 'payment_on_hold_by_user_id',
                'payment_on_hold_at', 'payment_on_hold_reason', 'payment_hold_released_by_user_id',
                'payment_hold_released_at', 'payment_hold_released_note']);
        });
    }
};
