<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('cumulative_quantity_received', 18, 4)->default(0)->after('cancelled_reason');
            $table->decimal('cumulative_quantity_accepted', 18, 4)->default(0)->after('cumulative_quantity_received');
            $table->decimal('over_receipt_tolerance_percent', 5, 2)->default(10.00)->after('cumulative_quantity_accepted');
            $table->timestamp('last_receipt_at')->nullable()->after('over_receipt_tolerance_percent');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'cumulative_quantity_received',
                'cumulative_quantity_accepted',
                'over_receipt_tolerance_percent',
                'last_receipt_at',
            ]);
        });
    }
};
