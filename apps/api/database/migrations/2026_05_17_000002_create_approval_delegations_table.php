<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('delegator_id');
            $table->index('delegate_id');
            $table->index('status');
            $table->index('starts_at');
            $table->index('ends_at');
            $table->index(['tenant_id', 'delegator_id', 'status']);
            $table->index(['tenant_id', 'delegate_id', 'status']);
            $table->index(['tenant_id', 'status', 'starts_at', 'ends_at'], 'approval_delegations_active_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
    }
};
