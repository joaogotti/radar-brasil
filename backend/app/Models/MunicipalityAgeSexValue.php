<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['municipality_id', 'reference_year', 'sex_code', 'age_group_id', 'population', 'percentage', 'population_status', 'percentage_status', 'population_source_value', 'percentage_source_value', 'last_synced_at'])]
class MunicipalityAgeSexValue extends Model
{
    protected function casts(): array
    {
        return ['population' => 'integer', 'percentage' => 'decimal:6', 'reference_year' => 'integer', 'sex_code' => 'integer', 'last_synced_at' => 'immutable_datetime'];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function ageGroup(): BelongsTo
    {
        return $this->belongsTo(AgeGroup::class);
    }
}
