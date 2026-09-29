<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Models\MunicipalityIndicatorValue;
use Illuminate\Http\JsonResponse;

class MunicipalityIndicatorController extends Controller
{
    public function index(string $ibgeCode): JsonResponse
    {
        $municipality = preg_match('/^[0-9]{7}$/D', $ibgeCode)
            ? Municipality::query()->with('state')->where('ibge_code', $ibgeCode)->first()
            : null;

        if ($municipality === null) {
            return response()->json(['message' => 'Município não encontrado.'], 404);
        }

        $indicators = $municipality->indicatorValues()->with('indicator')->get()
            ->sortBy([
                fn (MunicipalityIndicatorValue $a, MunicipalityIndicatorValue $b): int => strcmp($a->indicator->code, $b->indicator->code),
                fn (MunicipalityIndicatorValue $a, MunicipalityIndicatorValue $b): int => $b->reference_year <=> $a->reference_year,
            ])
            ->map(fn (MunicipalityIndicatorValue $value): array => [
                'code' => $value->indicator->code,
                'name' => $value->indicator->name,
                'value' => $value->value,
                'unit' => $value->indicator->unit,
                'reference_year' => $value->reference_year,
            ])->values();

        return response()->json([
            'municipality' => [
                'ibge_code' => $municipality->ibge_code,
                'name' => $municipality->name,
                'state' => [
                    'ibge_code' => $municipality->state->ibge_code,
                    'name' => $municipality->state->name,
                    'abbreviation' => $municipality->state->abbreviation,
                ],
            ],
            'indicators' => $indicators,
        ]);
    }
}
