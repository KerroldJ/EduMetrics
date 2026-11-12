<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Evaluation;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $admins = User::where('is_active', true)
                ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Admin'))
                ->orderBy('id')->lockForUpdate()->get();
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($account->hasRole('Admin') && $account->is_active && $admins->count() <= 1) {
                throw ValidationException::withMessages(['password' => 'Keep at least one active administrator. Create another Admin before deleting this account.']);
            }
            if (TeacherAssignment::where('teacher_id', $account->id)->exists()
                || Evaluation::where('student_id', $account->id)->orWhere('teacher_id', $account->id)->exists()
                || (Schema::hasTable('archived_sms_notifications') && DB::table('archived_sms_notifications')->where('user_id', $account->id)->exists())) {
                throw ValidationException::withMessages(['password' => 'This account has school records. Ask an administrator to deactivate it instead so evaluation history is preserved.']);
            }
            $account->delete();
        });
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
