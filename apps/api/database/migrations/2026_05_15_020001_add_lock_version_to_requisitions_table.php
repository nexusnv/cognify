<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};
