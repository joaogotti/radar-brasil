<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchMunicipalitiesRequest;
use App\Models\Municipality;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MunicipalityController extends Controller
{
    public function search(SearchMunicipalitiesRequest $request): JsonResponse
    {
        $parameters = $request->validated();
        $query = Municipality::query()->select(['id', 'state_id', 'ibge_code', 'name'])
            ->with('state:id,ibge_code,name,abbreviation');

        if (isset($parameters['state'])) {
            $state = State::query()->where('ibge_code', $parameters['state'])->first();

            if ($state === null) {
                return response()->json(['message' => 'UF não encontrada.'], 404);
            }

            $query->whereBelongsTo($state);
        }

        // SQLite cobre a lógica geral dos testes; acentos são validados no PostgreSQL.
        $postgres = $query->getConnection()->getDriverName() === 'pgsql';
        $nameExpression = $postgres ? 'lower(unaccent(name))' : 'lower(name)';
        $search = trim($parameters['search'] ?? '');

        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $parameterExpression = $postgres ? 'lower(unaccent(?))' : 'lower(?)';
            $query->whereRaw("{$nameExpression} LIKE {$parameterExpression} ESCAPE '!'", [$pattern]);
        }

        $results = $query->orderByRaw($nameExpression)->orderBy('ibge_code')
            ->paginate((int) ($parameters['per_page'] ?? 20), page: (int) ($parameters['page'] ?? 1))
            ->appends($request->safe()->except('page'))
            ->through(fn (Municipality $municipality): array => [
                'id' => $municipality->id,
                'ibge_code' => $municipality->ibge_code,
                'name' => $municipality->name,
                'state' => [
                    'ibge_code' => $municipality->state->ibge_code,
                    'name' => $municipality->state->name,
                    'abbreviation' => $municipality->state->abbreviation,
                ],
            ]);

        return response()->json($results);
    }

    public function index(State $state): JsonResponse
    {
        $municipalities = $state->municipalities()
            ->get(['id', 'state_id', 'ibge_code', 'name'])
            ->sortBy(fn (Municipality $municipality): string => Str::ascii($municipality->name), SORT_STRING | SORT_FLAG_CASE)
            ->values();

        return response()->json($municipalities);
    }
}
