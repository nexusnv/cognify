<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreignId('rfq_invitation_id')->nullable()->after('vendor_id')->constrained('rfq_invitations')->nullOnDelete();
            $table->string('submission_source')->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('submission_source');
            $table->foreignId('submitted_by_user_id')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->json('submitted_by_vendor_contact')->nullable()->after('submitted_by_user_id');
            $table->unsignedInteger('file_count')->default(0)->after('submitted_by_vendor_contact');
            $table->timestamp('latest_received_at')->nullable()->after('file_count');

            $table->unique(['tenant_id', 'rfq_invitation_id'], 'quotations_tenant_invitation_unique');
            $table->index(['tenant_id', 'rfq_id', 'vendor_id'], 'quotations_tenant_rfq_vendor_index');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropUnique('quotations_tenant_invitation_unique');
            $table->dropIndex('quotations_tenant_rfq_vendor_index');
            $table->dropConstrainedForeignId('rfq_invitation_id');
            $table->dropConstrainedForeignId('submitted_by_user_id');
            $table->dropColumn([
                'submission_source',
                'submitted_at',
                'submitted_by_vendor_contact',
                'file_count',
                'latest_received_at',
            ]);
        });
    }
};
