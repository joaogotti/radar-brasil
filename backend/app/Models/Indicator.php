<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'unit', 'source', 'source_type', 'source_aggregate_id', 'source_variable_id', 'source_research_id', 'source_indicator_id', 'source_methodological_reference', 'source_series'])]
class Indicator extends Model
{
    public function municipalityValues(): HasMany
    {
        return $this->hasMany(MunicipalityIndicatorValue::class);
    }

    public function stateValues(): HasMany
    {
        return $this->hasMany(StateIndicatorValue::class);
    }
}
