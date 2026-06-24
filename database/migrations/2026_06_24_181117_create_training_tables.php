<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('ministry')->nullable();
            $table->boolean('has_test')->default(false);
            $table->integer('passing_score')->default(80);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('training_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('image_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('video_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->string('image_path')->nullable();
            $table->string('type')->default('single'); // single or multiple choices correct
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('test_questions')->cascadeOnDelete();
            $table->text('option_text')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });

        Schema::create('training_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->timestamps();
            $table->unique(['training_id', 'user_id']);
        });

        Schema::create('training_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('training_assignments')->nullOnDelete();
            $table->integer('score');
            $table->boolean('passed');
            $table->json('answers_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_attempts');
        Schema::dropIfExists('training_assignments');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('test_questions');
        Schema::dropIfExists('training_steps');
        Schema::dropIfExists('trainings');
    }
};
