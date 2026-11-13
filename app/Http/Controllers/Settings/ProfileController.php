<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfilePasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $newPhotoPath = null;
        $oldPhotoPath = null;

        try {
            DB::transaction(function () use ($request, &$newPhotoPath, &$oldPhotoPath): void {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $oldPhotoPath = $user->avatar_path;
                $user->forceFill($request->safe()->except(['photo', 'remove_photo']));
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
                $photo = $request->file('photo');
                if ($photo instanceof UploadedFile) {
                    $newPhotoPath = $photo->store('profile-photos', 'public');
                    if ($newPhotoPath === false) {
                        throw ValidationException::withMessages(['photo' => 'The profile picture could not be saved. Please try again.']);
                    }
                    $user->avatar_path = $newPhotoPath;
                } elseif ($request->boolean('remove_photo')) {
                    $user->avatar_path = null;
                }
                $user->save();
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }
            throw $exception;
        }

        if ($oldPhotoPath && ($newPhotoPath || $request->boolean('remove_photo'))) {
            Storage::disk('public')->delete($oldPhotoPath);
        }
        $request->user()->refresh();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function updatePassword(ProfilePasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('profile.edit');
    }
}
