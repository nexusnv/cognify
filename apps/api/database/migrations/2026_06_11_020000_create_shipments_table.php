<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('number');
            $table->string('status');
            $table->string('carrier_name', 200)->nullable();
            $table->string('tracking_reference', 200)->nullable();
            $table->date('shipment_date');
            $table->date('estimated_arrival_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'purchase_order_id'], 'shipments_tenant_po_idx');
            $table->index(['tenant_id', 'status', 'shipment_date'], 'shipments_tenant_status_shipment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
