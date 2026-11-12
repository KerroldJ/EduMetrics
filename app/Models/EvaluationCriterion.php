<?php

namespace App\Models;

use Database\Factories\EvaluationCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evaluation_form_id', 'prompt', 'weight', 'position'])]
class EvaluationCriterion extends Model
{
    /** @use HasFactory<EvaluationCriterionFactory> */
    use HasFactory;

    protected $table = 'evaluation_criteria';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'position' => 'integer'];
    }

    /** @return BelongsTo<EvaluationForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'evaluation_form_id');
    }
}
