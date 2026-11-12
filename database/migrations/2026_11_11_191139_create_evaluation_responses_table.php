<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained('evaluation_criteria')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->unique(['evaluation_id', 'evaluation_criterion_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_responses');
    }
};
