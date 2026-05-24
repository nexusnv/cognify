<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_scoring_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignIdFor(User::class, 'created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignIdFor(User::class, 'updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'deactivated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name', 'is_active']);
        });

        Schema::create('quotation_scoring_template_criteria', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignUuid('template_id')
                ->constrained('quotation_scoring_templates')
                ->cascadeOnDelete();
            $table->string('category');
            $table->string('label');
            $table->text('guidance')->nullable();
            $table->decimal('weight', 8, 2);
            $table->unsignedInteger('max_score');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('display_order');
            $table->timestamps();

            $table->unique(['template_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_scoring_template_criteria');
        Schema::dropIfExists('quotation_scoring_templates');
    }
};
