<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->foreignId('strand_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('school_year', 9)->nullable();
            $table->unsignedTinyInteger('grade_level')->nullable();
            $table->string('section', 80)->nullable();
            $table->string('school_id', 80)->nullable()->unique();
            $table->string('mobile', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('strand_id');
            $table->dropColumn(['is_active', 'school_year', 'grade_level', 'section', 'school_id', 'mobile']);
        });
    }
};
