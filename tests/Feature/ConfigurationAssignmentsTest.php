<?php

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('legacy assignment links redirect to configuration and preserve pagination', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();

    $this->actingAs($admin)->get(route('assignments.index', ['page' => 2]))
        ->assertRedirectToRoute('strands.index', ['page' => 2, 'tab' => 'assignments']);
});

test('configuration includes paginated assignments and their form options', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create(['code' => 'STEM']);
    Section::factory()->create(['name' => 'Newton']);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    TeacherAssignment::factory()->count(21)->create(['teacher_id' => $teacher->id, 'strand_id' => $strand->id]);

    $this->actingAs($admin)->get(route('strands.index', ['tab' => 'assignments']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/Strands')->where('activeTab', 'assignments')
        ->where('availableTabs', ['school-years', 'strands', 'sections', 'assignments'])
        ->has('assignments.data', 20)->where('assignments.total', 21)
        ->where('assignments.data.0.teacher.name', $teacher->name)
        ->where('assignments.data.0.strand.code', 'STEM')
        ->where('assignments.data.0.grade_level', null)->where('assignments.data.0.section', null)
        ->where('assignmentOptions.teachers.0.id', $teacher->id)
        ->where('assignmentOptions.strands.0.id', $strand->id)
        ->where('assignmentOptions.sections.0.name', 'Newton')
        ->where('assignmentOptions.schoolYears.0.name', '2026-2027')
        ->where('assignments.next_page_url', route('strands.index', ['tab' => 'assignments', 'page' => 2])));

    $this->get(route('strands.index', ['tab' => 'assignments', 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('activeTab', 'assignments')->has('assignments.data', 1)->where('assignments.current_page', 2));
});

test('assignment managers access only the assignments tab and lookup data', function () {
    $this->seed(DemoAccountSeeder::class);
    $manager = User::factory()->create();
    $manager->givePermissionTo('assignments.manage');
    Strand::factory()->create();

    $this->actingAs($manager)->get(route('strands.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeTab', 'assignments')->where('availableTabs', ['assignments'])
            ->has('assignments')->has('assignmentOptions')
            ->missing('strands')->missing('sections')->missing('schoolYears'));
    $this->get(route('strands.index', ['tab' => 'strands']))->assertForbidden();
    $this->post(route('strands.store'), ['code' => 'STEM', 'name' => 'Science', 'is_active' => true])->assertForbidden();
});

test('strand managers cannot access assignment records or options', function () {
    $this->seed(DemoAccountSeeder::class);
    $manager = User::factory()->create();
    $manager->givePermissionTo('strands.manage');
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $assignment = TeacherAssignment::factory()->create();

    $this->actingAs($manager)->get(route('strands.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeTab', 'school-years')->where('availableTabs', ['school-years', 'strands', 'sections'])
            ->has('strands')->has('sections')->has('schoolYears')
            ->missing('assignments')->missing('assignmentOptions'));
    $this->get(route('strands.index', ['tab' => 'assignments']))->assertForbidden();
    $this->get(route('assignments.index'))->assertForbidden();
    $this->patch(route('assignments.status', $assignment), ['is_active' => false])->assertForbidden();
    expect($assignment->fresh()->is_active)->toBeTrue();
});

test('saving editing and changing assignment status return to the assignments tab', function () {
    $this->seed(DemoAccountSeeder::class);
    $manager = User::factory()->create();
    $manager->givePermissionTo('assignments.manage');
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $strand = Strand::factory()->create();
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $data = ['teacher_id' => $teacher->id, 'strand_id' => $strand->id, 'school_year' => '2026-2027', 'grade_level' => null, 'section' => null, 'is_active' => true];

    $this->actingAs($manager)->post(route('assignments.store'), $data)
        ->assertSessionHasNoErrors()->assertRedirectToRoute('strands.index', ['tab' => 'assignments']);
    $assignment = TeacherAssignment::where('teacher_id', $teacher->id)->firstOrFail();
    expect($assignment->grade_level)->toBeNull()->and($assignment->section)->toBeNull();

    $this->put(route('assignments.update', $assignment), [...$data, 'grade_level' => 11])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('strands.index', ['tab' => 'assignments']);
    expect($assignment->fresh()->grade_level)->toBe(11);

    $this->patch(route('assignments.status', $assignment), ['is_active' => false])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('strands.index', ['tab' => 'assignments']);
    expect($assignment->fresh()->is_active)->toBeFalse();
});
