<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_form_id')->constrained()->restrictOnDelete();
            $table->text('prompt');
            $table->decimal('weight', 8, 2)->default(1);
            $table->unsignedInteger('position');
            $table->unique(['evaluation_form_id', 'position']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
