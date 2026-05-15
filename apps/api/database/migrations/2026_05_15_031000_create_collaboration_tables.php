<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'subject_id']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('collaboration_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id');
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign(['tenant_id', 'comment_id'])
                ->references(['tenant_id', 'id'])
                ->on('collaboration_comments')
                ->cascadeOnDelete();
            $table->unique(['comment_id', 'mentioned_user_id']);
            $table->index(['tenant_id', 'mentioned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_mentions');
        Schema::dropIfExists('collaboration_comments');
    }
};
