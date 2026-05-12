<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->string('unit_of_measure');
            $table->decimal('estimated_unit_price', 14, 2);
            $table->char('currency', 3);
            $table->timestamps();
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE requisition_line_items ADD CONSTRAINT requisition_line_items_quantity_non_negative CHECK (quantity >= 0)');
            DB::statement('ALTER TABLE requisition_line_items ADD CONSTRAINT requisition_line_items_estimated_unit_price_non_negative CHECK (estimated_unit_price >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_line_items');
    }
};
