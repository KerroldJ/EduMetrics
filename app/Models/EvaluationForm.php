<?php

namespace App\Models;

use Database\Factories\EvaluationFormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property array<int, array{label: string, min: float|int|numeric-string, max: float|int|numeric-string}>|null $performance_bands */
#[Fillable(['name', 'description', 'scale_min', 'scale_max', 'performance_bands', 'is_active'])]
class EvaluationForm extends Model
{
    /** @use HasFactory<EvaluationFormFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scale_min' => 'integer', 'scale_max' => 'integer', 'performance_bands' => 'array', 'is_active' => 'boolean'];
    }

    /** @return HasMany<EvaluationCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class, 'evaluation_form_id');
    }

    /** @return HasMany<EvaluationPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(EvaluationPeriod::class, 'evaluation_form_id');
    }
}
