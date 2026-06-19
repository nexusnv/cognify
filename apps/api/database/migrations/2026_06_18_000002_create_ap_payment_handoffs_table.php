<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_handoffs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number', 50);
            $table->string('status', 50)->default('draft');
            $table->date('effective_payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('currency', 3);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->string('remittance_reference', 255)->nullable();
            $table->foreignIdFor(User::class, 'created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'ready_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->foreignIdFor(User::class, 'last_exported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_exported_at')->nullable();
            $table->string('last_export_format', 10)->nullable();
            $table->json('snapshot')->nullable();
            $table->json('readiness_warnings')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'status', 'effective_payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_handoffs');
    }
};
