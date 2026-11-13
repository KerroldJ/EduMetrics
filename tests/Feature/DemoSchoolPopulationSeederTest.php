<?php

use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoSchoolPopulationSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('the population seeder creates sixteen teachers and twenty enrolled students with matching assignments', function () {
    $this->seed(DemoSchoolPopulationSeeder::class);
    $teachers = User::role('Teacher')->get();
    $students = User::role('Student')->get();

    $this->assertDatabaseCount('users', 36);
    expect($teachers)->toHaveCount(16);
    expect($students)->toHaveCount(20);
    $this->assertDatabaseCount('teacher_assignments', 16);
    $this->assertDatabaseCount('evaluation_periods', 0);
    expect($teachers->pluck('strand_id')->unique())->toHaveCount(4);
    expect($teachers->pluck('section')->unique())->toHaveCount(4);
    expect($students->pluck('strand_id')->unique())->toHaveCount(4);
    expect($students->pluck('section')->unique())->toHaveCount(4);
    foreach ($students as $student) {
        expect($student->school_year)->not->toBeNull();
        expect($student->grade_level)->toBeIn([11, 12]);
        expect($student->eligibleAssignments()->exists())->toBeTrue();
        expect($student->hasVerifiedEmail())->toBeTrue();
        expect($student->is_active)->toBeTrue();
    }
    foreach ($teachers as $teacher) {
        expect($teacher->hasVerifiedEmail())->toBeTrue();
        expect($teacher->is_active)->toBeTrue();
        expect(TeacherAssignment::where('teacher_id', $teacher->id)->where('strand_id', $teacher->strand_id)->where('section', $teacher->section)->exists())->toBeTrue();
    }
    expect(Hash::check('password', User::where('email', 'teacher01@edumetric.test')->firstOrFail()->password))->toBeTrue();
    expect(Hash::check('password', User::where('email', 'student20@edumetric.test')->firstOrFail()->password))->toBeTrue();
});

test('repeated population seeding preserves passwords enrollments and retired assignments', function () {
    $this->seed(DemoSchoolPopulationSeeder::class);
    $teacher = User::where('email', 'teacher01@edumetric.test')->firstOrFail();
    $student = User::where('email', 'student01@edumetric.test')->firstOrFail();
    $teacher->forceFill(['password' => 'ChangedTeacherPassword!', 'is_active' => false])->save();
    $student->forceFill(['password' => 'ChangedStudentPassword!', 'grade_level' => 12])->save();
    TeacherAssignment::where('teacher_id', $teacher->id)->update(['is_active' => false]);
    $usersBefore = User::orderBy('id')->get()->toArray();
    $assignmentsBefore = TeacherAssignment::orderBy('id')->get()->toArray();

    $this->seed(DemoSchoolPopulationSeeder::class);

    expect(User::orderBy('id')->get()->toArray())->toBe($usersBefore);
    expect(Hash::check('ChangedTeacherPassword!', $teacher->fresh()->password))->toBeTrue();
    expect(Hash::check('ChangedStudentPassword!', $student->fresh()->password))->toBeTrue();
    expect(TeacherAssignment::orderBy('id')->get()->toArray())->toBe($assignmentsBefore);
    $this->assertDatabaseCount('users', 36);
    $this->assertDatabaseCount('teacher_assignments', 16);
});

test('population seeding uses the open evaluation school year and enables the new student teacher list', function () {
    $this->freezeTime();
    $activeYear = SchoolYear::factory()->create(['name' => '2026-2027']);
    SchoolYear::factory()->create(['name' => '2027-2028']);
    $form = EvaluationForm::factory()->create();
    $form->criteria()->create(['prompt' => 'Explains clearly', 'weight' => 1, 'position' => 1]);
    $period = EvaluationPeriod::factory()->create(['school_year' => $activeYear->name, 'evaluation_form_id' => $form->id, 'status' => 'open', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

    $this->seed(DemoSchoolPopulationSeeder::class);
    $student = User::where('email', 'student01@edumetric.test')->firstOrFail();
    expect(User::role('Student')->where('school_year', '2026-2027')->count())->toBe(20);
    expect(User::role('Teacher')->where('school_year', '2026-2027')->count())->toBe(16);
    $this->actingAs($student)->get(route('evaluations.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('teachers', 1)->where('teachers.0.evaluations.0.period_id', $period->id));
});

test('population seeding only uses configured active strands and sections', function () {
    $active = Strand::factory()->create(['code' => 'ACTIVE']);
    $inactive = Strand::factory()->create(['code' => 'RETIRED', 'is_active' => false]);
    Section::factory()->create(['name' => 'School section']);

    $this->seed(DemoSchoolPopulationSeeder::class);

    expect(User::where('strand_id', $active->id)->count())->toBe(36);
    expect(User::where('strand_id', $inactive->id)->count())->toBe(0);
    expect(User::where('section', 'School section')->count())->toBe(36);
    $this->assertDatabaseCount('strands', 2);
    $this->assertDatabaseCount('sections', 1);
});

test('population seeding skips shared environments without creating demo data', function (string $environment) {
    $this->app->instance('env', $environment);

    $this->seed(DemoSchoolPopulationSeeder::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('teacher_assignments', 0);
    $this->assertDatabaseCount('roles', 0);
    $this->assertDatabaseCount('school_years', 0);
})->with(['production', 'staging']);

test('population seeding does not create accounts when all configured strands are retired', function () {
    Strand::factory()->create(['is_active' => false]);
    Section::factory()->create();

    expect(fn () => $this->seed(DemoSchoolPopulationSeeder::class))->toThrow(RuntimeException::class, 'Configure at least one active strand');
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('teacher_assignments', 0);
});
