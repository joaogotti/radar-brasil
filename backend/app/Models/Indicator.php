<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'unit', 'source', 'source_aggregate_id', 'source_variable_id'])]
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
