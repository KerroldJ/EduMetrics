<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evaluation_forms', function (Blueprint $table): void {
            $table->dropColumn('performance_bands');
        });

        DB::table('evaluation_forms')
            ->where('description', 'like', '%Survey bands apply to the displayed two-decimal mean.%')
            ->orWhere('description', 'like', '%DepEd Memorandum 89, s. 2025 (PMES rating ranges)%')
            ->update(['description' => DB::raw("REPLACE(REPLACE(description, ' Survey bands apply to the displayed two-decimal mean.', ''), '; DepEd Memorandum 89, s. 2025 (PMES rating ranges)', '')")]);
    }

    /**
     * Restore the column structure. Removed classifications and obsolete instructions are not restored.
     */
    public function down(): void
    {
        Schema::table('evaluation_forms', function (Blueprint $table): void {
            $table->json('performance_bands')->nullable();
        });
    }
};
