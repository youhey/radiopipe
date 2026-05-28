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
        Schema::create('episodes', function (Blueprint $table): void {
            $table->id();
            $table->string('episode_key')->unique();
            $table->date('date');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('processed_at');
            $table->foreignId('character_profile_id')->nullable()->constrained('character_profiles')->nullOnDelete();
            $table->string('character_key')->nullable();
            $table->string('status');
            $table->string('title')->nullable();
            $table->string('language')->default('ja');
            $table->unsignedInteger('target_duration_seconds')->nullable();
            $table->unsignedInteger('estimated_duration_seconds')->nullable();
            $table->json('scenario_json');
            $table->json('metadata');
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index('status');
            $table->index('character_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
