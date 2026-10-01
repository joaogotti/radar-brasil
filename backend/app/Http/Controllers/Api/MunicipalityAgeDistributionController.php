<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;

class MunicipalityAgeDistributionController extends Controller
{
    public function index(string $ibgeCode): JsonResponse
    {
        $municipality = preg_match('/^[0-9]{7}$/D', $ibgeCode)
            ? Municipality::with('state')->where('ibge_code', $ibgeCode)->first() : null;
        if ($municipality === null) {
            return response()->json(['message' => 'Município não encontrado.'], 404);
        }
        $year = config('demography.year');
        $values = $municipality->ageSexValues()->with('ageGroup')->where('reference_year', $year)->get()
            ->sortBy([fn ($a, $b) => $a->ageGroup->sort_order <=> $b->ageGroup->sort_order, fn ($a, $b) => $a->sex_code <=> $b->sex_code]);
        $available = $values->sum(fn ($row) => (int) ($row->population_status === 'available') + (int) ($row->percentage_status === 'available'));
        $availability = $values->isEmpty() ? 'not_loaded' : ($available === 0 ? 'unavailable' : ($values->count() === 42 && $available === 84 ? 'complete' : 'partial'));

        return response()->json([
            'municipality' => ['ibge_code' => $municipality->ibge_code, 'name' => $municipality->name, 'state' => [
                'ibge_code' => $municipality->state->ibge_code, 'name' => $municipality->state->name, 'abbreviation' => $municipality->state->abbreviation,
            ]],
            'reference_year' => $year, 'availability' => $availability,
            'source' => ['name' => 'IBGE - Censo Demográfico', 'aggregate_id' => config('demography.pyramid.aggregate'),
                'population_variable_id' => config('demography.pyramid.variables.population'), 'percentage_variable_id' => config('demography.pyramid.variables.percentage'), 'percentage_basis' => 'total_municipal_population'],
            'data' => $values->map(fn ($row) => [
                'sex_code' => $row->sex_code, 'sex_label' => config('demography.sexes')[$row->sex_code],
                'age_group' => ['ibge_code' => $row->ageGroup->ibge_code, 'label' => $row->ageGroup->label, 'order' => $row->ageGroup->sort_order, 'min_age' => $row->ageGroup->min_age, 'max_age' => $row->ageGroup->max_age],
                'population' => $row->population, 'population_status' => $row->population_status,
                'percentage' => $row->percentage, 'percentage_status' => $row->percentage_status,
            ])->values(),
        ]);
    }
}
