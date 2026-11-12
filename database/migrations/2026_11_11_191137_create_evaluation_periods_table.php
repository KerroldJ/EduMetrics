<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('school_year', 9);
            $table->foreignId('evaluation_form_id')->constrained()->restrictOnDelete();
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('minimum_responses')->default(3);
            $table->timestamp('released_at')->nullable();
            $table->index(['status', 'opens_at', 'closes_at']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_periods');
    }
};
