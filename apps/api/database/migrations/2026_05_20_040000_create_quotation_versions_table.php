<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Quotation::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 32);
            $table->string('submission_source', 32)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignIdFor(User::class, 'submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('submitted_by_vendor_contact')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('superseded_at')->nullable();
            $table->string('quotation_reference')->nullable();
            $table->date('quoted_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('subtotal_amount', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->text('warranty_terms')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('compliance_notes')->nullable();
            $table->text('buyer_notes')->nullable();
            $table->text('vendor_notes')->nullable();
            $table->boolean('manual_entry_complete')->default(false);
            $table->json('manual_entry_missing_fields')->nullable();
            $table->json('attachment_snapshots')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'quotation_id', 'version_number'], 'quotation_versions_number_unique');
            $table->index(['tenant_id', 'quotation_id', 'is_current'], 'quotation_versions_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_versions');
    }
};
