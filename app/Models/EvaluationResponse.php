<?php

namespace App\Models;

use Database\Factories\EvaluationResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evaluation_id', 'evaluation_criterion_id', 'rating'])]
class EvaluationResponse extends Model
{
    /** @use HasFactory<EvaluationResponseFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    /** @return BelongsTo<EvaluationCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }
}
