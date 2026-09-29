<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ibge_code', 'name', 'abbreviation'])]
class State extends Model
{
    protected function casts(): array
    {
        return [
            'ibge_code' => 'integer',
        ];
    }
}
