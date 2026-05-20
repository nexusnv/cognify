<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->string('quotation_reference')->nullable()->after('number');
            $table->date('quoted_at')->nullable()->after('submitted_at');
            $table->date('valid_until')->nullable()->after('quoted_at');
            $table->decimal('subtotal_amount', 14, 2)->nullable()->after('currency');
            $table->decimal('tax_amount', 14, 2)->nullable()->after('subtotal_amount');
            $table->decimal('freight_amount', 14, 2)->nullable()->after('tax_amount');
            $table->decimal('discount_amount', 14, 2)->nullable()->after('freight_amount');
            $table->string('payment_terms')->nullable()->after('latest_received_at');
            $table->string('delivery_terms')->nullable()->after('payment_terms');
            $table->unsignedInteger('lead_time_days')->nullable()->after('delivery_terms');
            $table->text('warranty_terms')->nullable()->after('lead_time_days');
            $table->text('exclusions')->nullable()->after('warranty_terms');
            $table->text('compliance_notes')->nullable()->after('exclusions');
            $table->text('buyer_notes')->nullable()->after('compliance_notes');
            $table->text('vendor_notes')->nullable()->after('buyer_notes');
            $table->boolean('manual_entry_complete')->default(false)->after('vendor_notes');
            $table->json('manual_entry_missing_fields')->nullable()->after('manual_entry_complete');
            $table->timestamp('manual_entry_saved_at')->nullable()->after('manual_entry_missing_fields');
            $table->string('manual_entry_saved_source')->nullable()->after('manual_entry_saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn([
                'quotation_reference',
                'quoted_at',
                'valid_until',
                'subtotal_amount',
                'tax_amount',
                'freight_amount',
                'discount_amount',
                'payment_terms',
                'delivery_terms',
                'lead_time_days',
                'warranty_terms',
                'exclusions',
                'compliance_notes',
                'buyer_notes',
                'vendor_notes',
                'manual_entry_complete',
                'manual_entry_missing_fields',
                'manual_entry_saved_at',
                'manual_entry_saved_source',
            ]);
        });
    }
};
