<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_projects', function (Blueprint $table): void {
            $table->text('charter')->nullable()->after('name');
            $table->string('department')->nullable()->after('currency');
            $table->string('cost_center')->nullable()->after('department');
            $table->date('target_start_date')->nullable()->after('cost_center');
            $table->date('target_completion_date')->nullable()->after('target_start_date');
            $table->timestamp('cancelled_at')->nullable()->after('target_completion_date');
            $table->foreignId('cancelled_by_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by_id');
            $table->timestamp('completed_at')->nullable()->after('cancellation_reason');
            $table->foreignId('completed_by_id')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'owner_id']);
            $table->index(['tenant_id', 'department']);
            $table->index(['tenant_id', 'cost_center']);
        });
    }

    public function down(): void
    {
        Schema::table('procurement_projects', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'owner_id']);
            $table->dropIndex(['tenant_id', 'department']);
            $table->dropIndex(['tenant_id', 'cost_center']);
            $table->dropConstrainedForeignId('completed_by_id');
            $table->dropColumn('completed_at');
            $table->dropColumn('cancellation_reason');
            $table->dropConstrainedForeignId('cancelled_by_id');
            $table->dropColumn('cancelled_at');
            $table->dropColumn('target_completion_date');
            $table->dropColumn('target_start_date');
            $table->dropColumn('cost_center');
            $table->dropColumn('department');
            $table->dropColumn('charter');
        });
    }
};
