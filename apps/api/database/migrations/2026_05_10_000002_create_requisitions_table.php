<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('number');
            $table->string('title');
            $table->text('business_justification')->nullable();
            $table->date('needed_by_date')->nullable();
            $table->string('department')->nullable();
            $table->string('project_id')->nullable();
            $table->string('cost_center')->nullable();
            $table->text('delivery_location')->nullable();
            $table->char('currency', 3)->default('MYR');
            $table->string('status');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'requester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
