<?php

namespace Database\Seeders;

use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class DemoSchoolPopulationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            if (Strand::doesntExist()) {
                $this->call(StrandSeeder::class);
            }
            if (Section::doesntExist()) {
                $this->call(SectionSeeder::class);
            }
            $strands = Strand::where('is_active', true)->orderBy('id')->get();
            $sections = Section::orderBy('name')->get();
            if ($strands->isEmpty()) {
                throw new RuntimeException('Configure at least one active strand before seeding demo school accounts.');
            }
            if (Role::where('guard_name', 'web')->whereIn('name', ['Teacher', 'Student'])->count() !== 2) {
                $this->call(RolePermissionSeeder::class);
            }

            $schoolYear = $this->schoolYear();
            $passwordHash = Hash::make('password');
            $cohorts = [];
            foreach ($strands as $strand) {
                foreach ($sections as $section) {
                    $cohorts[] = ['strand_id' => (int) $strand->id, 'section' => $section->name];
                }
            }
            shuffle($cohorts);
            $teacherIds = [];
            for ($index = 0; $index < 16; $index++) {
                $email = sprintf('teacher%02d@edumetric.test', $index + 1);
                $teacher = User::where('email', $email)->first();
                if (! $teacher) {
                    $cohort = $cohorts[$index % count($cohorts)];
                    $enrollment = [...$cohort, 'school_year' => $schoolYear, 'grade_level' => null];
                    $teacher = $this->createAccount($email, 'Teacher', sprintf('DEMO-TCH-%03d', $index + 1), $enrollment, $passwordHash);
                    TeacherAssignment::create(['teacher_id' => $teacher->id, ...$enrollment, 'is_active' => true]);
                }
                $teacherIds[] = $teacher->id;
            }

            $assignments = TeacherAssignment::whereIn('teacher_id', $teacherIds)->where('school_year', $schoolYear)->where('is_active', true)
                ->whereHas('strand', fn ($query) => $query->where('is_active', true))
                ->whereHas('teacher', fn ($query) => $query->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('name', 'Teacher')->where('guard_name', 'web')))
                ->orderBy('id')->get()->shuffle()->all();
            for ($index = 0; $index < 20; $index++) {
                $email = sprintf('student%02d@edumetric.test', $index + 1);
                if (User::where('email', $email)->exists()) {
                    continue;
                }
                if ($assignments === []) {
                    throw new RuntimeException('At least one active demo teacher assignment is required to enroll new demo students.');
                }
                $assignment = $assignments[$index % count($assignments)];
                $this->createAccount($email, 'Student', sprintf('DEMO-STU-%03d', $index + 1), [
                    'strand_id' => (int) $assignment->strand_id,
                    'school_year' => $schoolYear,
                    'grade_level' => $assignment->grade_level ?? (fake()->boolean() ? 11 : 12),
                    'section' => $assignment->section ?? $sections->random()->name,
                ], $passwordHash);
            }
        });
    }

    private function schoolYear(): string
    {
        $schoolYear = EvaluationPeriod::where('status', 'open')->where('opens_at', '<=', now())->where('closes_at', '>', now())
            ->orderByDesc('id')->value('school_year') ?? SchoolYear::orderByDesc('name')->value('name');
        if (is_string($schoolYear)) {
            return $schoolYear;
        }

        return SchoolYear::firstOrCreate(['name' => now()->year.'-'.(now()->year + 1)])->name;
    }

    /** @param array{strand_id: int, school_year: string, grade_level: int|null, section: string} $enrollment */
    private function createAccount(string $email, string $role, string $schoolId, array $enrollment, string $passwordHash): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'school_id' => $schoolId,
            'password' => $passwordHash,
            'is_active' => true,
            ...$enrollment,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
