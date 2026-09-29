<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['indicator_id', 'municipality_id', 'value', 'reference_year'])]
class MunicipalityIndicatorValue extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:6', 'reference_year' => 'integer'];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
