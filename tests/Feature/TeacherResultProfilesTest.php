<?php

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('teacher result profiles show active strand and section assignments and handle unassigned teachers', function () {
    $this->seed(DemoAccountSeeder::class);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $unassigned = User::factory()->create(['name' => 'Z Unassigned Teacher']);
    $unassigned->assignRole('Teacher');
    Section::factory()->create(['name' => 'A']);
    $stem = Strand::factory()->create(['code' => 'STEM']);
    $abm = Strand::factory()->create(['code' => 'ABM']);
    $retired = Strand::factory()->create(['is_active' => false]);
    TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $stem->id, 'section' => 'A', 'grade_level' => 11]);
    TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $abm->id, 'section' => null, 'grade_level' => null]);
    TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $abm->id, 'is_active' => false]);
    TeacherAssignment::factory()->create(['teacher_id' => $teacher->id, 'strand_id' => $retired->id]);

    $this->actingAs($admin)->get(route('results.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('teachers', 2)
        ->where('teachers.0.id', $teacher->id)
        ->has('teachers.0.assignments', 2)
        ->where('teachers.0.assignments.0.strand', ['code' => 'STEM', 'name' => $stem->name])
        ->where('teachers.0.assignments.0.section', 'A')
        ->where('teachers.0.assignments.0.grade_level', 11)
        ->where('teachers.0.assignments.0.school_year', '2026-2027')
        ->where('teachers.0.assignments.1.strand.code', 'ABM')
        ->where('teachers.0.assignments.1.section', null)
        ->where('teachers.0.assignments.1.grade_level', null)
        ->where('teachers.0.evaluated_students_count', 0)
        ->where('teachers.1.id', $unassigned->id)
        ->has('teachers.1.assignments', 0)
        ->where('teachers.1.evaluated_students_count', 0));
});

test('teacher result profiles count unique students across periods without exposing responses or student identities', function () {
    $this->seed(DemoAccountSeeder::class);
    SchoolYear::factory()->create(['name' => '2026-2027']);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();
    $teacher = User::where('email', 'teacher@edumetric.test')->firstOrFail();
    $student = User::where('email', 'student@edumetric.test')->firstOrFail();
    $anotherStudent = User::factory()->create();
    $anotherTeacher = User::factory()->create(['name' => 'Z Other Teacher']);
    $anotherTeacher->assignRole('Teacher');
    $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id]);
    $otherAssignment = TeacherAssignment::factory()->create(['teacher_id' => $anotherTeacher->id]);
    $form = EvaluationForm::factory()->create();
    $openPeriod = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'status' => 'open']);
    $closedPeriod = EvaluationPeriod::factory()->create(['evaluation_form_id' => $form->id, 'status' => 'closed']);
    $evaluation = Evaluation::factory()->create(['teacher_id' => $teacher->id, 'student_id' => $student->id, 'teacher_assignment_id' => $assignment->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $openPeriod->id]);
    Evaluation::factory()->create([...$evaluation->only(['teacher_id', 'student_id', 'teacher_assignment_id', 'evaluation_form_id']), 'evaluation_period_id' => $closedPeriod->id]);
    Evaluation::factory()->create([...$evaluation->only(['teacher_id', 'teacher_assignment_id', 'evaluation_form_id', 'evaluation_period_id']), 'student_id' => $anotherStudent->id]);
    Evaluation::factory()->create(['teacher_id' => $anotherTeacher->id, 'student_id' => $student->id, 'teacher_assignment_id' => $otherAssignment->id, 'evaluation_form_id' => $form->id, 'evaluation_period_id' => $openPeriod->id]);

    $this->actingAs($admin)->get(route('results.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('teachers.0.id', $teacher->id)
        ->where('teachers.0.evaluated_students_count', 2)
        ->where('teachers.1.id', $anotherTeacher->id)
        ->where('teachers.1.evaluated_students_count', 1)
        ->missing('teachers.0.students')
        ->missing('teachers.0.evaluations')
        ->has('results', 0));
});
