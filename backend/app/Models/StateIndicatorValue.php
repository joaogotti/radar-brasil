<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['indicator_id', 'state_id', 'value', 'reference_year'])]
class StateIndicatorValue extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:6', 'reference_year' => 'integer'];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }
}
