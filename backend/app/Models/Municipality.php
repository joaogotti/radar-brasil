<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['state_id', 'ibge_code', 'name'])]
class Municipality extends Model
{
    public function ageSexValues(): HasMany
    {
        return $this->hasMany(MunicipalityAgeSexValue::class);
    }

    public function indicatorValues(): HasMany
    {
        return $this->hasMany(MunicipalityIndicatorValue::class);
    }

    protected function casts(): array
    {
        return [
            'state_id' => 'integer',
            'ibge_code' => 'integer',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }
}
