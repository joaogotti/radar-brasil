<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MunicipalityController extends Controller
{
    public function index(State $state): JsonResponse
    {
        $municipalities = $state->municipalities()
            ->get(['id', 'state_id', 'ibge_code', 'name'])
            ->sortBy(fn (Municipality $municipality): string => Str::ascii($municipality->name), SORT_STRING | SORT_FLAG_CASE)
            ->values();

        return response()->json($municipalities);
    }
}
