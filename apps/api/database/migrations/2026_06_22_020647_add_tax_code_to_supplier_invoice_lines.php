<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supplier_invoice_lines', function (Blueprint $table): void {
            $table->string('tax_code', 50)->nullable()->after('line_subtotal');
            $table->decimal('tax_amount', 18, 4)->default(0)->after('tax_code');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_lines', function (Blueprint $table): void {
            $table->dropColumn(['tax_code', 'tax_amount']);
        });
    }
};
