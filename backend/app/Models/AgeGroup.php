<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ibge_code', 'label', 'sort_order', 'min_age', 'max_age'])]
class AgeGroup extends Model
{
    protected function casts(): array
    {
        return ['ibge_code' => 'integer', 'sort_order' => 'integer', 'min_age' => 'integer', 'max_age' => 'integer'];
    }

    public function ageSexValues(): HasMany
    {
        return $this->hasMany(MunicipalityAgeSexValue::class);
    }
}
