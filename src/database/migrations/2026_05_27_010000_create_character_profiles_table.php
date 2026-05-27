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
        Schema::create('character_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('character_key', 100)->unique();
            $table->string('name', 100);
            $table->string('role');
            $table->text('personality');
            $table->text('tone');
            $table->json('speech_style');
            $table->json('catchphrases');
            $table->json('style_examples');
            $table->json('banned_phrases');
            $table->json('disallowed_expressions');
            $table->json('serious_topic_behavior');
            $table->json('content_policy');
            $table->json('script_preferences');
            $table->json('metadata');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_profiles');
    }
};
