<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_url', 2048)->nullable()->after('email');
            $table->string('timezone', 64)->default('UTC')->after('avatar_url');
            $table->string('locale', 12)->default('en')->after('timezone');
            $table->string('theme', 16)->default('system')->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_url', 'timezone', 'locale', 'theme']);
        });
    }
};
