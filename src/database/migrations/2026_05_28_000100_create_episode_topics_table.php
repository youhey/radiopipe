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
        Schema::create('episode_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->cascadeOnDelete();
            $table->string('topic_id');
            $table->string('upstream_provider')->nullable();
            $table->string('upstream_id')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_type')->nullable();
            $table->string('title')->nullable();
            $table->text('url')->nullable();
            $table->string('screening_status')->nullable();
            $table->string('editorial_status')->nullable();
            $table->string('scenario_selection_status')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('topic_draft_json')->nullable();
            $table->json('screening_json')->nullable();
            $table->json('editorial_json')->nullable();
            $table->json('scenario_selection_json')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('episode_id');
            $table->index('topic_id');
            $table->index(['upstream_provider', 'upstream_id']);
            $table->index('screening_status');
            $table->index('editorial_status');
            $table->index('scenario_selection_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episode_topics');
    }
};
