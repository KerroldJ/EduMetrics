<?php

namespace Database\Seeders;

use App\Models\SchoolYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolYearSeeder extends Seeder
{
    public function run(): void
    {
        $years = DB::table('users')->whereNotNull('school_year')->where('school_year', '!=', '')->select('school_year')
            ->union(DB::table('teacher_assignments')->select('school_year'))
            ->union(DB::table('evaluation_periods')->select('school_year'))->get();

        foreach ($years as $row) {
            SchoolYear::firstOrCreate(['name' => $row->school_year]);
        }
    }
}
