<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('teacher_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('evaluation_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('evaluation_form_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 10, 4);
            $table->text('comment')->nullable();
            $table->json('context');
            $table->timestamp('submitted_at');
            $table->unique(['student_id', 'teacher_id', 'evaluation_period_id'], 'evaluations_student_teacher_period_unique');
            $table->index(['teacher_id', 'evaluation_period_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
