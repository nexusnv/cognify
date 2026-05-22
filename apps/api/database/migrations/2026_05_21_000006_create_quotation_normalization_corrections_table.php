<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationNormalizationIssue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_normalization_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationNormalization::class, 'normalization_id')->constrained('quotation_normalizations')->cascadeOnDelete();
            $table->foreignIdFor(QuotationNormalizationIssue::class, 'issue_id')->nullable()->constrained('quotation_normalization_issues')->nullOnDelete();
            $table->string('field_path');
            $table->json('original_raw_value')->nullable();
            $table->json('previous_normalized_value')->nullable();
            $table->json('corrected_value')->nullable();
            $table->foreignIdFor(User::class, 'corrected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('correction_note');
            $table->timestamps();

            $table->index(['tenant_id', 'normalization_id', 'field_path'], 'quotation_normalization_corrections_field_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_normalization_corrections');
    }
};
