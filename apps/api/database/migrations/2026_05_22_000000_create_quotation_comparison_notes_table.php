<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_comparison_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Rfq::class)->constrained('rfqs')->cascadeOnDelete();
            $table->foreignIdFor(Quotation::class)->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignIdFor(Vendor::class)->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('rfq_line_item_id')->nullable();
            $table->string('section', 32);
            $table->text('note');
            $table->foreignIdFor(User::class, 'created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignIdFor(User::class, 'updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'rfq_id', 'section'], 'quotation_comparison_notes_rfq_section_index');
            $table->index(['tenant_id', 'quotation_id'], 'quotation_comparison_notes_quotation_index');
            $table->index(['tenant_id', 'vendor_id'], 'quotation_comparison_notes_vendor_index');
            $table->index(['tenant_id', 'rfq_line_item_id'], 'quotation_comparison_notes_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_comparison_notes');
    }
};
