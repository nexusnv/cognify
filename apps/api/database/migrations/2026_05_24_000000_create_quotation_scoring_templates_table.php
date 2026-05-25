<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE quotation_scoring_templates ADD active_name VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN is_active = 1 THEN name ELSE NULL END) STORED'
            );
            DB::statement(
                'CREATE UNIQUE INDEX quotation_scoring_templates_active_name_unique ON quotation_scoring_templates (tenant_id, active_name)'
            );
        } else {
            $whereActive = $driver === 'pgsql' ? 'is_active = true' : 'is_active = 1';
            DB::statement(
                "CREATE UNIQUE INDEX quotation_scoring_templates_active_name_unique ON quotation_scoring_templates (tenant_id, name) WHERE {$whereActive}"
            );
        }

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
            $table->softDeletes();

            $table->index(['template_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_scoring_template_criteria');
        if (Schema::hasTable('quotation_scoring_templates')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('DROP INDEX quotation_scoring_templates_active_name_unique ON quotation_scoring_templates');
            } else {
                DB::statement('DROP INDEX IF EXISTS quotation_scoring_templates_active_name_unique');
            }
        }
        Schema::dropIfExists('quotation_scoring_templates');
    }
};
