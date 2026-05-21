<?php

use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreignIdFor(QuotationVersion::class, 'current_version_id')
                ->nullable()
                ->after('rfq_invitation_id')
                ->constrained('quotation_versions')
                ->nullOnDelete();
            $table->unsignedInteger('version_count')->default(0)->after('current_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropColumn('version_count');
        });
    }
};
