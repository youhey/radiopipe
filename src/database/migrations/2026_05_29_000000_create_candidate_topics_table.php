<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('candidate_topics', function (Blueprint $table): void {
            $table->id();
            $table->string('topic_id')->unique();
            $table->string('source_type')->nullable();
            $table->string('source_name')->nullable();
            $table->string('upstream_provider')->nullable();
            $table->string('upstream_id')->nullable();
            $table->text('article_url')->nullable();
            $table->dateTime('article_published_at')->nullable();
            $table->json('topic_draft_json')->nullable();
            $table->json('screening_json')->nullable();
            $table->json('editorial_json')->nullable();
            $table->string('screening_status')->nullable();
            $table->integer('screening_score')->nullable();
            $table->string('editorial_status')->nullable();
            $table->integer('editorial_score')->nullable();
            $table->string('candidate_fingerprint', 64);
            $table->dateTime('processed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('topic_id');
            $table->index(['upstream_provider', 'upstream_id']);
            $table->index('screening_status');
            $table->index('editorial_status');
            $table->index('candidate_fingerprint');
            $table->index('processed_at');
            $table->index('article_published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_topics');
    }
};
