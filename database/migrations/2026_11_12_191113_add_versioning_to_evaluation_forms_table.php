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
        Schema::table('evaluation_forms', function (Blueprint $table): void {
            $table->foreignId('template_id')->nullable()->constrained('evaluation_forms')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->unique(['template_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_forms', function (Blueprint $table): void {
            $table->dropUnique(['template_id', 'version']);
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('version');
        });
    }
};
