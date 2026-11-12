<?php

namespace App\Models;

use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_id', 'teacher_id', 'teacher_assignment_id', 'evaluation_period_id', 'evaluation_form_id', 'score', 'comment', 'context', 'submitted_at'])]
class Evaluation extends Model
{
    /** @use HasFactory<EvaluationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['score' => 'decimal:4', 'context' => 'array', 'submitted_at' => 'datetime'];
    }

    /** @return BelongsTo<EvaluationPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class, 'evaluation_period_id');
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** @return HasMany<EvaluationResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(EvaluationResponse::class, 'evaluation_id');
    }

    /** @return array<int, array{teacher_id: int, teacher: string, period_id: int, period: string, school_year: string, status: string, count: int, score: float, template_name: string, template_version: int, scale_max: int, performance_level: ?string, criteria: array<int, array{prompt: string, average: float}>, comments: array<int, string>}> */
    public static function summaries(?int $periodId = null, ?int $teacherId = null, bool $releasedOnly = false): array
    {
        $query = static::query()->select(['teacher_id', 'evaluation_period_id'])->selectRaw('COUNT(*) AS response_count, AVG(score) AS average_score')
            ->when($periodId, fn ($query) => $query->where('evaluation_period_id', $periodId))
            ->when($teacherId, fn ($query) => $query->where('teacher_id', $teacherId))
            ->when($releasedOnly, fn ($query) => $query->whereHas('period', fn ($query) => $query->where('status', 'released')))
            ->with(['teacher:id,name', 'period.form.criteria'])->groupBy('teacher_id', 'evaluation_period_id')->orderByDesc('evaluation_period_id')->orderBy('teacher_id');

        return $query->get()->filter(fn (self $row): bool => ! $releasedOnly || (int) $row->getAttribute('response_count') >= $row->period->minimum_responses)->map(function (self $row) use ($releasedOnly, $periodId, $teacherId): array {
            $score = round((float) $row->getAttribute('average_score'), 2);
            $form = $row->period->form;
            $band = collect($form->performance_bands ?? [])->first(fn (array $band): bool => $score >= (float) $band['min'] && $score <= (float) $band['max']);
            $detail = $releasedOnly || ($periodId && $teacherId);
            $criteria = [];
            $comments = [];
            if ($detail) {
                $averages = EvaluationResponse::query()->join('evaluations', 'evaluations.id', '=', 'evaluation_responses.evaluation_id')->where('evaluations.teacher_id', $row->teacher_id)->where('evaluations.evaluation_period_id', $row->evaluation_period_id)->select('evaluation_criterion_id')->selectRaw('AVG(rating) AS average_rating')->groupBy('evaluation_criterion_id')->get()->keyBy('evaluation_criterion_id');
                $criteria = $form->criteria->sortBy('position')->map(fn (EvaluationCriterion $criterion): array => ['prompt' => $criterion->prompt, 'average' => round((float) $averages->get($criterion->id)?->getAttribute('average_rating'), 2)])->values()->all();
                $comments = static::where('teacher_id', $row->teacher_id)->where('evaluation_period_id', $row->evaluation_period_id)->whereNotNull('comment')->where('comment', '!=', '')->orderBy('comment')->limit(100)->pluck('comment')->map(fn ($comment): string => (string) $comment)->all();
            }

            return ['teacher_id' => (int) $row->teacher_id, 'teacher' => $row->teacher->name, 'period_id' => (int) $row->evaluation_period_id, 'period' => $row->period->name, 'school_year' => $row->period->school_year, 'status' => $row->period->status, 'count' => (int) $row->getAttribute('response_count'), 'score' => $score, 'template_name' => $form->name, 'template_version' => $form->version, 'scale_max' => $form->scale_max, 'performance_level' => $band['label'] ?? null, 'criteria' => $criteria, 'comments' => $comments];
        })->values()->all();
    }
}
