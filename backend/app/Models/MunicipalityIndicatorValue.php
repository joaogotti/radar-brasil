<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['indicator_id', 'municipality_id', 'value', 'reference_year', 'status', 'source_value', 'last_synced_at'])]
class MunicipalityIndicatorValue extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:6', 'reference_year' => 'integer', 'last_synced_at' => 'immutable_datetime'];
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
