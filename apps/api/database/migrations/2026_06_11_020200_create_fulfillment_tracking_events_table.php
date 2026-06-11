<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_tracking_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('occurred_at');
            $table->string('location', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'shipment_id', 'occurred_at'], 'fulfillment_tracking_tenant_shipment_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_tracking_events');
    }
};
