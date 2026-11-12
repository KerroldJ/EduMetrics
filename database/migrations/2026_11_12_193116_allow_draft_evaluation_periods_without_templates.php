<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_periods', function (Blueprint $table): void {
            $table->unsignedBigInteger('evaluation_form_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('evaluation_periods')->whereNull('evaluation_form_id')->exists()) {
            throw new RuntimeException('Assign a template to every draft period before rolling back this migration.');
        }

        Schema::table('evaluation_periods', function (Blueprint $table): void {
            $table->unsignedBigInteger('evaluation_form_id')->nullable(false)->change();
        });
    }
};
