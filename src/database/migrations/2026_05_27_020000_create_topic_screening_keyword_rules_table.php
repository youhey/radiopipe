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
        Schema::create('topic_screening_keyword_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_type', 50);
            $table->string('keyword');
            $table->string('match_type', 50);
            $table->json('target_fields');
            $table->integer('penalty')->nullable();
            $table->string('severity', 50)->nullable();
            $table->string('action', 50);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rule_type', 'keyword', 'match_type'], 'topic_screening_keyword_rules_unique_rule');
            $table->index('rule_type');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topic_screening_keyword_rules');
    }
};
