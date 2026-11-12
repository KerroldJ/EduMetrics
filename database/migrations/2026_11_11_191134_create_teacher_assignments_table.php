<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('strand_id')->constrained()->restrictOnDelete();
            $table->string('school_year', 9);
            $table->unsignedTinyInteger('grade_level')->nullable();
            $table->string('section', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->index(['strand_id', 'school_year', 'is_active']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
