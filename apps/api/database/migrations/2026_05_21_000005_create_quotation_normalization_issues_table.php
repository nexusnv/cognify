<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationNormalization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_normalization_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationNormalization::class, 'normalization_id')->constrained('quotation_normalizations')->cascadeOnDelete();
            $table->string('severity', 32);
            $table->string('field_path')->nullable();
            $table->string('issue_code');
            $table->text('message');
            $table->json('raw_value')->nullable();
            $table->json('suggested_value')->nullable();
            $table->string('status', 32);
            $table->foreignIdFor(User::class, 'resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'normalization_id', 'severity', 'status'], 'quotation_normalization_issues_status_index');
            $table->index(['tenant_id', 'issue_code'], 'quotation_normalization_issue_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_normalization_issues');
    }
};
