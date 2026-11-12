<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $audience = match (true) {
            $user->can('users.manage') => 'admin',
            $user->can('results.view-own') => 'teacher',
            $user->can('evaluations.submit') => 'student',
            default => 'member',
        };

        $activity = [];
        if ($audience === 'admin') {
            $activity = [
                'strandCount' => Strand::where('is_active', true)->count(),
                'assignmentCount' => TeacherAssignment::where('is_active', true)->count(),
                'formCount' => EvaluationForm::where('is_active', true)->whereHas('criteria')->count(),
                'openPeriodCount' => EvaluationPeriod::where('status', 'open')->where('opens_at', '<=', now())->where('closes_at', '>', now())->count(),
                'submissionCount' => Evaluation::count(),
            ];
        } elseif ($audience === 'student') {
            $pending = 0;
            foreach (EvaluationPeriod::where('school_year', $user->school_year)->where('status', 'open')->where('opens_at', '<=', now())->where('closes_at', '>', now())->get() as $period) {
                $teachers = $user->eligibleAssignments($period)->pluck('teacher_id')->unique();
                $submitted = Evaluation::where('student_id', $user->id)->where('evaluation_period_id', $period->id)->pluck('teacher_id');
                $pending += $teachers->diff($submitted)->count();
            }
            $activity = ['pendingCount' => $pending, 'submittedCount' => Evaluation::where('student_id', $user->id)->count()];
        } elseif ($audience === 'teacher') {
            $released = Evaluation::where('teacher_id', $user->id)->whereHas('period', fn (Builder $query): Builder => $query->where('status', 'released'))
                ->select('evaluation_period_id')->selectRaw('COUNT(*) AS response_count')->with('period')->groupBy('evaluation_period_id')->get()
                ->filter(fn (Evaluation $evaluation): bool => (int) $evaluation->getAttribute('response_count') >= $evaluation->period->minimum_responses)->count();
            $activity = ['releasedCount' => $released];
        }

        return Inertia::render('Dashboard', [
            'audience' => $audience,
            'activity' => $activity,
            'roleLabel' => $user->getRoleNames()->first() ?? 'Access pending',
            'overview' => $audience === 'admin' ? [
                'teacherCount' => User::query()->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Teacher')->where('guard_name', 'web'))->count(),
                'studentCount' => User::query()->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Student')->where('guard_name', 'web'))->count(),
                'accountCount' => User::query()->count(),
                'verifiedCount' => User::query()->whereNotNull('email_verified_at')->count(),
                'recentAccounts' => User::query()
                    ->select(['id', 'name', 'email', 'email_verified_at'])
                    ->with('roles:id,name')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get()
                    ->map(fn (User $account): array => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'email' => $account->email,
                        'verified' => $account->hasVerifiedEmail(),
                        'roles' => $account->getRoleNames()->values()->all(),
                    ]),
            ] : null,
        ]);
    }
}
