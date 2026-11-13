<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Strand;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(['Admin', 'Teacher', 'Student'])]]);
        $query = User::query()->with('roles:id,name')->when($filters['q'] ?? null, fn (Builder $query, string $term): Builder => $query->where(fn (Builder $query): Builder => $query->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))->when($filters['role'] ?? null, fn (Builder $query, string $role): Builder => $query->whereHas('roles', fn (Builder $query): Builder => $query->where('name', $role)));

        return Inertia::render('admin/Users', ['users' => $query->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (User $user): array => [...$user->only(['id', 'name', 'email', 'strand_id', 'school_year', 'grade_level', 'section', 'school_id', 'mobile', 'is_active', 'is_approved', 'avatar']), 'role' => $user->getRoleNames()->first(), 'email_verified' => $user->hasVerifiedEmail()]), 'strands' => Strand::orderBy('name')->get(['id', 'name', 'code', 'is_active']), 'filters' => $filters, 'sections' => Section::orderBy('name')->get(['id', 'name']), 'schoolYears' => SchoolYear::orderByDesc('name')->get(['id', 'name'])]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        Gate::authorize('roles.assign');
        $this->saveAccount($request);

        return to_route('users.index')->with('success', 'School account created.');
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('roles.assign');
        $this->saveAccount($request, $user);

        return to_route($request->user()->id === $user->id ? 'dashboard' : 'users.index')->with('success', 'Account updated.');
    }

    public function approve(User $user): RedirectResponse
    {
        Gate::authorize('roles.assign');
        $user->forceFill(['is_approved' => true])->save();

        return to_route('users.index')->with('success', 'Account approved. The user may now sign in if their account is active.');
    }

    private function saveAccount(SaveUserRequest $request, ?User $user = null): void
    {
        $data = $request->validated();
        $newPhotoPath = null;
        $oldPhotoPath = null;

        try {
            DB::transaction(function () use ($request, $data, $user, &$newPhotoPath, &$oldPhotoPath): void {
                if ($user) {
                    $admins = User::where('is_active', true)->where('is_approved', true)->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Admin'))->orderBy('id')->lockForUpdate()->get();
                    $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    if ($user->hasRole('Admin') && $user->is_active && $user->is_approved && ($data['role'] !== 'Admin' || ! $request->boolean('is_active')) && $admins->count() <= 1) {
                        throw ValidationException::withMessages(['role' => 'Keep at least one active administrator.']);
                    }
                } else {
                    $user = new User;
                }

                $fields = collect($data)->except(['role', 'password_confirmation', 'email_verified', 'photo', 'remove_photo'])->all();
                if ($user->exists && empty($fields['password'])) {
                    unset($fields['password']);
                }
                $emailChanged = $user->email !== $data['email'];
                $oldPhotoPath = $user->avatar_path;
                $photo = $request->file('photo');
                if ($photo instanceof UploadedFile) {
                    $newPhotoPath = $photo->store('profile-photos', 'public');
                    if ($newPhotoPath === false) {
                        throw ValidationException::withMessages(['photo' => 'The profile picture could not be saved. Please try again.']);
                    }
                    $fields['avatar_path'] = $newPhotoPath;
                } elseif ($request->boolean('remove_photo')) {
                    $fields['avatar_path'] = null;
                }
                $user->forceFill($fields);
                if (! $user->exists) {
                    $user->email_verified_at = $request->boolean('email_verified', true) ? now() : null;
                } elseif ($request->has('email_verified')) {
                    $user->email_verified_at = $request->boolean('email_verified') ? ($emailChanged ? now() : ($user->email_verified_at ?? now())) : null;
                } elseif ($emailChanged) {
                    $user->email_verified_at = null;
                }
                $user->save();
                $user->syncRoles([$data['role']]);
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
    }
}
