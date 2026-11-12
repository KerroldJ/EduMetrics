<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $name) {
            Section::firstOrCreate(['name' => $name]);
        }

        $names = DB::table('users')->whereNotNull('section')->where('section', '!=', '')->select('section')
            ->union(DB::table('teacher_assignments')->whereNotNull('section')->where('section', '!=', '')->select('section'))->get();

        foreach ($names as $row) {
            Section::firstOrCreate(['name' => $row->section]);
        }
    }
}
