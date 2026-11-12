<?php

use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

test('SMS routes permissions commands and executable jobs are removed', function () {
    $this->seed(DemoAccountSeeder::class);
    $admin = User::where('email', 'admin@edumetric.test')->firstOrFail();

    $this->actingAs($admin)->get('/sms')->assertNotFound();
    $this->actingAs($admin)->post('/sms', [])->assertNotFound();

    expect(Route::has('sms.index'))->toBeFalse()
        ->and(Route::has('sms.store'))->toBeFalse()
        ->and(array_key_exists('evaluations:send-reminders', Artisan::all()))->toBeFalse()
        ->and(class_exists('App\\Jobs\\SendEvaluationSms'))->toBeFalse();
    $this->assertDatabaseMissing('permissions', ['name' => 'sms.manage']);
});

test('retirement preserves message history and cancels only SMS queue work', function () {
    $this->seed(DemoAccountSeeder::class);
    $user = User::factory()->create();
    if (Schema::hasTable('archived_sms_notifications')) {
        Schema::rename('archived_sms_notifications', 'sms_notifications');
    }
    DB::table('sms_notifications')->insert([
        'user_id' => $user->id, 'mobile' => '+639171234567', 'message' => 'Historical reminder',
        'status' => 'pending', 'scheduled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    Permission::findOrCreate('sms.manage', 'web');
    $user->givePermissionTo('sms.manage');
    foreach (['App\\Jobs\\SendEvaluationSms', 'App\\Jobs\\UnrelatedJob'] as $job) {
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => json_encode(['displayName' => $job]),
            'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp,
        ]);
    }
    $migration = require database_path('migrations/2026_11_11_202121_retire_sms_notifications.php');

    $migration->up();

    expect(Schema::hasTable('sms_notifications'))->toBeFalse();
    $this->assertDatabaseHas('archived_sms_notifications', ['user_id' => $user->id, 'message' => 'Historical reminder', 'status' => 'cancelled']);
    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseHas('jobs', ['payload' => json_encode(['displayName' => 'App\\Jobs\\UnrelatedJob'])]);
    $this->assertDatabaseMissing('permissions', ['name' => 'sms.manage']);
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('archived message history does not cause account deletion errors', function () {
    $user = User::factory()->create();
    DB::table('archived_sms_notifications')->insert([
        'user_id' => $user->id, 'mobile' => '+639171234567', 'message' => 'Archived record',
        'status' => 'accepted', 'scheduled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');

    $this->assertModelExists($user);
    $this->assertDatabaseHas('archived_sms_notifications', ['user_id' => $user->id, 'message' => 'Archived record']);
});
