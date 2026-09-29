<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class StateController extends Controller
{
    public function index(): JsonResponse
    {
        $states = State::query()
            ->get(['id', 'ibge_code', 'name', 'abbreviation'])
            ->sortBy(fn (State $state): string => Str::ascii($state->name), SORT_STRING | SORT_FLAG_CASE)
            ->values();

        return response()->json($states);
    }
}
