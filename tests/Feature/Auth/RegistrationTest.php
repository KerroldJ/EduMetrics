<?php

use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new students register pending approval without signing in', function () {
    $strand = Strand::factory()->create();
    $section = Section::factory()->create();
    $year = SchoolYear::factory()->create();
    $this->seed(RolePermissionSeeder::class);
    Event::fake([Login::class]);
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'Student',
        'strand_id' => $strand->id,
        'section' => $section->name,
        'grade_level' => 11,
        'is_approved' => true,
        'email_verified_at' => now(),
    ]);

    $this->assertGuest();
    $response->assertRedirectToRoute('login')->assertSessionHas('status', 'Your account has been created and is awaiting administrator approval.');
    $student = User::where('email', 'test@example.com')->firstOrFail();
    expect($student->is_approved)->toBeFalse()
        ->and($student->hasRole('Student'))->toBeTrue()
        ->and($student->grade_level)->toBe(11)
        ->and($student->strand_id)->toBe($strand->id)
        ->and($student->school_year)->toBe($year->name)
        ->and($student->section)->toBe($section->name)
        ->and($student->email_verified_at)->toBeNull();
    Event::assertNotDispatched(Login::class);
});
