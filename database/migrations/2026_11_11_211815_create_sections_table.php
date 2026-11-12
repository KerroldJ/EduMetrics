<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });

        foreach (['users', 'teacher_assignments'] as $table) {
            DB::table($table)->where('section', '')->update(['section' => null]);
        }

        $names = DB::table('users')->whereNotNull('section')->select('section')
            ->union(DB::table('teacher_assignments')->whereNotNull('section')->select('section'))->get();
        foreach ($names as $row) {
            DB::table('sections')->insert(['name' => $row->section, 'created_at' => now(), 'updated_at' => now()]);
        }

        foreach (['users', 'teacher_assignments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('section')->references('name')->on('sections')->cascadeOnUpdate()->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'teacher_assignments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['section']);
            });
        }

        Schema::dropIfExists('sections');
    }
};
