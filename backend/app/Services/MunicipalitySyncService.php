<?php

namespace App\Services;

use App\Models\Municipality;
use App\Models\State;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class MunicipalitySyncService
{
    public function sync(): int
    {
        $data = Http::acceptJson()
            ->connectTimeout(10)
            ->timeout(60)
            ->retry(3, 500)
            ->get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios')
            ->throw()
            ->json();

        Validator::make(['municipalities' => $data], [
            'municipalities' => ['required', 'array', 'list', 'min:1'],
            'municipalities.*' => ['required', 'array'],
            'municipalities.*.id' => ['required', 'integer', 'digits:7', 'distinct'],
            'municipalities.*.nome' => ['required', 'string', 'max:100'],
        ])->validate();

        $states = State::query()->pluck('id', 'ibge_code');
        $rows = [];

        foreach ($data as $municipality) {
            $stateCode = data_get($municipality, 'regiao-imediata.regiao-intermediaria.UF.id')
                ?? data_get($municipality, 'microrregiao.mesorregiao.UF.id');

            if (! is_numeric($stateCode) || ! $states->has($stateCode)) {
                throw new RuntimeException('UF ausente ou não cadastrada para o município '.$municipality['id'].'. Execute o StateSeeder e verifique a resposta do IBGE.');
            }

            $rows[] = [
                'state_id' => $states->get($stateCode),
                'ibge_code' => (int) $municipality['id'],
                'name' => $municipality['nome'],
            ];
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                Municipality::upsert($chunk, ['ibge_code'], ['state_id', 'name']);
            }
        });

        return count($rows);
    }
}
