<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\Section;
use App\Models\Strand;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(['Admin', 'Teacher', 'Student'])]]);
        $query = User::query()->with('roles:id,name')->when($filters['q'] ?? null, fn (Builder $query, string $term): Builder => $query->where(fn (Builder $query): Builder => $query->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))->when($filters['role'] ?? null, fn (Builder $query, string $role): Builder => $query->whereHas('roles', fn (Builder $query): Builder => $query->where('name', $role)));

        return Inertia::render('admin/Users', ['users' => $query->orderByDesc('id')->paginate(20)->withQueryString()->through(fn (User $user): array => [...$user->only(['id', 'name', 'email', 'strand_id', 'school_year', 'grade_level', 'section', 'school_id', 'mobile', 'is_active']), 'role' => $user->getRoleNames()->first(), 'email_verified' => $user->hasVerifiedEmail()]), 'strands' => Strand::orderBy('name')->get(['id', 'name', 'code', 'is_active']), 'filters' => $filters, 'sections' => Section::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        Gate::authorize('roles.assign');
        $data = $request->validated();
        DB::transaction(function () use ($data, $request): void {
            $user = new User;
            $user->forceFill(collect($data)->except(['role', 'password_confirmation', 'email_verified'])->all());
            $user->email_verified_at = $request->boolean('email_verified', true) ? now() : null;
            $user->save();
            $user->syncRoles([$data['role']]);
        });

        return to_route('users.index')->with('success', 'School account created.');
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('roles.assign');
        $data = $request->validated();
        DB::transaction(function () use ($user, $data, $request): void {
            $admins = User::where('is_active', true)->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Admin'))->orderBy('id')->lockForUpdate()->get();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->hasRole('Admin') && $user->is_active && ($data['role'] !== 'Admin' || ! $request->boolean('is_active')) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active administrator.']);
            } $fields = collect($data)->except(['role', 'password_confirmation', 'email_verified'])->all();
            if (empty($fields['password'])) {
                unset($fields['password']);
            } $emailChanged = $user->email !== $data['email'];
            $user->forceFill($fields);
            if ($request->has('email_verified')) {
                $user->email_verified_at = $request->boolean('email_verified') ? ($emailChanged ? now() : ($user->email_verified_at ?? now())) : null;
            } elseif ($emailChanged) {
                $user->email_verified_at = null;
            } $user->save();
            $user->syncRoles([$data['role']]);
        });

        return to_route($request->user()->id === $user->id ? 'dashboard' : 'users.index')->with('success', 'Account updated.');
    }
}
