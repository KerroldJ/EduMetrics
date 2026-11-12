<?php

use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('administrators can create edit list and delete unused sections', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();

    $this->actingAs($admin)->post(route('sections.store'), ['name' => '  Newton  '])->assertSessionHasNoErrors()->assertRedirectToRoute('strands.index', ['tab' => 'sections']);
    $section = Section::where('name', 'Newton')->firstOrFail();
    $this->get(route('strands.index', ['tab' => 'sections']))->assertInertia(fn (Assert $page) => $page->component('admin/Strands')->where('activeTab', 'sections')->where('sections.0.name', 'Newton'));
    $this->post(route('sections.store'), ['name' => 'Newton'])->assertSessionHasErrors('name');
    $this->put(route('sections.update', $section), ['name' => 'Einstein'])->assertSessionHasNoErrors()->assertRedirect();
    expect($section->fresh()->name)->toBe('Einstein');
    $this->delete(route('sections.destroy', $section))->assertSessionHasNoErrors()->assertRedirect();
    $this->assertModelMissing($section);
});

test('section renames keep student enrollment and teacher eligibility connected', function () {
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $section = Section::factory()->create(['name' => 'Original']);
    $strand = Strand::factory()->create();
    $student->forceFill(['strand_id' => $strand->id, 'school_year' => '2026-2027', 'section' => $section->name])->save();
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'section' => $section->name]);
    $form = EvaluationForm::factory()->create();
    $period = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'status' => 'open']);

    $this->actingAs($admin)->put(route('sections.update', $section), ['name' => 'Renamed'])->assertSessionHasNoErrors();

    expect($student->fresh()->section)->toBe('Renamed');
    expect($assignment->fresh()->section)->toBe('Renamed');
    $this->actingAs($student->fresh())->get(route('evaluations.index'))->assertInertia(fn (Assert $page) => $page->where('enrollment.section', 'Renamed')->where('periods.0.assignments.0.id', $assignment->id));
});

test('sections used by accounts or assignments cannot be deleted', function (string $usage) {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $section = Section::factory()->create();
    if ($usage === 'account') {
        User::factory()->create()->forceFill(['section' => $section->name])->save();
    } else {
        SchoolYear::factory()->create(['name' => '2026-2027']);
        TeacherAssignment::factory()->create(['section' => $section->name]);
    }

    $this->actingAs($admin)->delete(route('sections.destroy', $section))->assertSessionHasErrors('section');

    $this->assertModelExists($section);
})->with(['account', 'assignment']);

test('user management lists managed sections and rejects unknown selections', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $section = Section::factory()->create(['name' => 'Newton']);
    $data = ['name' => 'Enrolled Student', 'email' => 'enrolled@student.test', 'password' => 'SchoolPassword123!', 'password_confirmation' => 'SchoolPassword123!', 'role' => 'Student', 'section' => 'Unknown', 'is_active' => true];

    $this->actingAs($admin)->get(route('users.index'))->assertInertia(fn (Assert $page) => $page->has('sections', 1)->where('sections.0.name', 'Newton'));
    $this->post(route('users.store'), $data)->assertSessionHasErrors('section');
    $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    $this->post(route('users.store'), [...$data, 'section' => $section->name])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['email' => $data['email'], 'section' => 'Newton']);
});

test('teacher assignments use the managed section list and allow all sections', function () {
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create();
    $section = Section::factory()->create();
    $data = ['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'section' => 'Unknown', 'is_active' => true];

    $this->actingAs($admin)->get(route('assignments.index'))->assertInertia(fn (Assert $page) => $page->where('sections.0.name', $section->name));
    $this->post(route('assignments.store'), $data)->assertSessionHasErrors('section');
    $this->assertDatabaseCount('teacher_assignments', 0);
    $this->post(route('assignments.store'), [...$data, 'section' => $section->name])->assertSessionHasNoErrors();
    $this->post(route('assignments.store'), [...$data, 'section' => null])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('teacher_assignments', 2);
});

test('students and teachers cannot manage sections', function (string $email) {
    $this->seed(DemoAccountSeeder::class);
    $account = User::where('email', $email)->firstOrFail();
    $section = Section::factory()->create(['name' => 'Original']);

    $this->actingAs($account)->post(route('sections.store'), ['name' => 'New'])->assertForbidden();
    $this->put(route('sections.update', $section), ['name' => 'Changed'])->assertForbidden();
    $this->delete(route('sections.destroy', $section))->assertForbidden();
    expect($section->fresh()->name)->toBe('Original');
    $this->assertDatabaseCount('sections', 1);
})->with(['student@edumetric.test', 'teacher@edumetric.test']);

test('section names must be nonempty unique and at most eighty characters', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $section = Section::factory()->create(['name' => 'Existing']);
    $other = Section::factory()->create(['name' => 'Another']);

    $this->actingAs($admin)->post(route('sections.store'), ['name' => '   '])->assertSessionHasErrors('name');
    $this->post(route('sections.store'), ['name' => str_repeat('a', 81)])->assertSessionHasErrors('name');
    $this->put(route('sections.update', $other), ['name' => $section->name])->assertSessionHasErrors('name');
    $this->put(route('sections.update', $section), ['name' => $section->name])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('sections', 2);
});

test('the section migration imports existing enrollment and assignment names', function () {
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $migration = require database_path('migrations/2026_11_11_211815_create_sections_table.php');
    $migration->down();
    $student = User::factory()->create();
    $student->forceFill(['section' => 'Legacy student section'])->save();
    $assignment = TeacherAssignment::factory()->create(['section' => 'Legacy assignment section']);
    $empty = User::factory()->create();
    $empty->forceFill(['section' => ''])->save();

    $migration->up();

    $this->assertDatabaseHas('sections', ['name' => 'Legacy student section']);
    $this->assertDatabaseHas('sections', ['name' => 'Legacy assignment section']);
    expect($student->fresh()->section)->toBe('Legacy student section');
    expect($assignment->fresh()->section)->toBe('Legacy assignment section');
    expect($empty->fresh()->section)->toBeNull();
});
