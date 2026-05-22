<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_normalizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Quotation::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationVersion::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('normalization_revision');
            $table->string('status', 32);
            $table->boolean('is_current_for_version')->default(true);
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('normalized_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable();
            $table->string('algorithm_version', 32)->default('deterministic-v1');
            $table->unsignedInteger('job_attempt_count')->default(0);
            $table->text('last_job_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'quotation_version_id', 'normalization_revision'], 'quotation_normalization_revision_unique');
            $table->index(['tenant_id', 'status', 'updated_at'], 'quotation_normalization_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_normalizations');
    }
};
