<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_notifications')) {
            Schema::rename('sms_notifications', 'archived_sms_notifications');
        }
        if (Schema::hasTable('archived_sms_notifications')) {
            DB::table('archived_sms_notifications')->whereIn('status', ['pending', 'sending'])->update([
                'status' => 'cancelled',
                'error' => 'Notification feature removed; queued delivery cancelled.',
                'updated_at' => now(),
            ]);
        }
        foreach (['jobs', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->select(['id', 'payload'])->orderBy('id')->chunkById(100, function ($jobs) use ($table): void {
                foreach ($jobs as $job) {
                    $payload = json_decode($job->payload, true);
                    if (is_array($payload) && (($payload['displayName'] ?? null) === 'App\\Jobs\\SendEvaluationSms' || ($payload['data']['commandName'] ?? null) === 'App\\Jobs\\SendEvaluationSms')) {
                        DB::table($table)->where('id', $job->id)->delete();
                    }
                }
            });
        }
        DB::table('permissions')->where('name', 'sms.manage')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Restore the legacy schema; cancelled delivery work stays cancelled. */
    public function down(): void
    {
        if (Schema::hasTable('archived_sms_notifications')) {
            Schema::rename('archived_sms_notifications', 'sms_notifications');
        }
        $permissionId = DB::table('permissions')->where('name', 'sms.manage')->where('guard_name', 'web')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId(['name' => 'sms.manage', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        $adminId = DB::table('roles')->where('name', 'Admin')->where('guard_name', 'web')->value('id');
        if ($adminId) {
            DB::table('role_has_permissions')->updateOrInsert(['role_id' => $adminId, 'permission_id' => $permissionId]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
