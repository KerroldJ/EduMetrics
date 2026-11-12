<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 9)->unique();
            $table->timestamps();
        });

        DB::table('users')->where('school_year', '')->update(['school_year' => null]);
        $years = DB::table('users')->whereNotNull('school_year')->select('school_year')
            ->union(DB::table('teacher_assignments')->select('school_year'))
            ->union(DB::table('evaluation_periods')->select('school_year'))->get();
        foreach ($years as $row) {
            DB::table('school_years')->insert(['name' => $row->school_year, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (['users', 'teacher_assignments', 'evaluation_periods'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('school_year')->references('name')->on('school_years')->cascadeOnUpdate()->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'teacher_assignments', 'evaluation_periods'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['school_year']);
            });
        }

        Schema::dropIfExists('school_years');
    }
};
