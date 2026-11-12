<?php

namespace Database\Seeders;

use App\Models\Strand;
use Illuminate\Database\Seeder;

class StrandSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'STEM' => 'Science, Technology, Engineering, and Mathematics',
            'ABM' => 'Accountancy, Business, and Management',
            'HUMSS' => 'Humanities and Social Sciences',
            'TVL' => 'Technical-Vocational-Livelihood',
        ] as $code => $name) {
            Strand::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
