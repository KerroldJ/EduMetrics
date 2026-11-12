<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EvaluationPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|CarbonImmutable $opens_at
 * @property Carbon|CarbonImmutable $closes_at
 * @property Carbon|CarbonImmutable|null $released_at
 */
#[Fillable(['name', 'school_year', 'evaluation_form_id', 'opens_at', 'closes_at', 'status', 'minimum_responses', 'released_at'])]
class EvaluationPeriod extends Model
{
    /** @use HasFactory<EvaluationPeriodFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'released_at' => 'datetime', 'minimum_responses' => 'integer'];
    }

    /** @return BelongsTo<EvaluationForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'evaluation_form_id');
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'evaluation_period_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->opens_at->lte(now()) && $this->closes_at->gt(now());
    }
}
