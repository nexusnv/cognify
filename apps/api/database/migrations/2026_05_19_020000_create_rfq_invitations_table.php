<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('status');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'rfq_id']);
            $table->index(['tenant_id', 'vendor_id']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'rfq_id', 'vendor_id'], 'rfq_invitation_tenant_rfq_vendor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_invitations');
    }
};
