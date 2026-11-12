<?php

namespace App\Policies;

use App\Models\EvaluationPeriod;
use App\Models\TeacherAssignment;
use App\Models\User;

class EvaluationPolicy
{
    public function submit(User $user, EvaluationPeriod $period, TeacherAssignment $assignment): bool
    {
        return $user->can('evaluations.submit') && $user->eligibleAssignments($period)->whereKey($assignment->id)->exists();
    }
}
