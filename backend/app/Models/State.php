<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ibge_code', 'name', 'abbreviation'])]
class State extends Model
{
    public function indicatorValues(): HasMany
    {
        return $this->hasMany(StateIndicatorValue::class);
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }

    protected function casts(): array
    {
        return [
            'ibge_code' => 'integer',
        ];
    }
}
