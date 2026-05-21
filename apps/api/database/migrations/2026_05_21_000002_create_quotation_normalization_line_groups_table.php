<?php

use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_normalization_line_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationNormalization::class, 'normalization_id')->constrained('quotation_normalizations')->cascadeOnDelete();
            $table->unsignedInteger('group_number');
            $table->string('pricing_mode', 32);
            $table->text('description')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('bundle_total_amount', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'normalization_id', 'group_number'], 'quotation_normalization_line_groups_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_normalization_line_groups');
    }
};
