<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'unit', 'source', 'source_type', 'source_aggregate_id', 'source_variable_id', 'source_research_id', 'source_indicator_id', 'source_methodological_reference', 'source_series', 'source_filters', 'source_universe', 'source_definition'])]
class Indicator extends Model
{
    protected function casts(): array
    {
        return ['source_filters' => 'array'];
    }

    public function municipalityValues(): HasMany
    {
        return $this->hasMany(MunicipalityIndicatorValue::class);
    }

    public function stateValues(): HasMany
    {
        return $this->hasMany(StateIndicatorValue::class);
    }
}
