<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class IbgeCensusAggregateReader
{
    public int $requests = 0;

    public int $attempts = 0;

    public function read(int $aggregate, array $variables, array $filters, array $municipalities, ?array $codes = null): array
    {
        // HTTP response cycles can retain decoded national batches between requests.
        gc_collect_cycles();
        $year = config('demography.year');
        $query = ['localidades' => 'N6['.($codes === null ? 'all' : implode(',', $codes)).']'];
        if ($filters !== []) {
            $query['classificacao'] = implode('|', array_map(fn ($id) => $id.'['.implode(',', $filters[$id]).']', array_keys($filters)));
        }
        $this->requests++;
        $payload = Http::acceptJson()->connectTimeout(10)->timeout(90)
            ->beforeSending(function (): void {
                $this->attempts++;
            })
            ->retry(3, 500, fn (Throwable $e): bool => $e instanceof ConnectionException || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())))
            ->get('https://servicodados.ibge.gov.br/api/v3/agregados/'.$aggregate.'/periodos/'.$year.'/variaveis/'.implode('|', array_keys($variables)), $query)
            ->throw()->json();
        if (! is_array($payload) || ! array_is_list($payload) || count($payload) !== count($variables)) {
            throw new RuntimeException('Unexpected census variables or invalid JSON response.');
        }
        ksort($filters);
        $expectedResults = array_product(array_map('count', $filters));
        $mapped = [];
        foreach ($payload as $variable) {
            $id = data_get($variable, 'id');
            if (! is_scalar($id) || ! isset($variables[$id]) || isset($mapped[$id]) || data_get($variable, 'unidade') !== $variables[$id]
                || ! is_array(data_get($variable, 'resultados')) || ! array_is_list($variable['resultados']) || count($variable['resultados']) !== $expectedResults) {
                throw new RuntimeException('Unexpected census variable, unit or category count.');
            }
            $mapped[$id] = [];
            foreach ($variable['resultados'] as $result) {
                $classifications = data_get($result, 'classificacoes');
                if (! is_array($classifications) || ! array_is_list($classifications) || count($classifications) !== count($filters)) {
                    throw new RuntimeException('Unexpected census classifications.');
                }
                $categories = [];
                foreach ($classifications as $classification) {
                    $classId = data_get($classification, 'id');
                    $category = data_get($classification, 'categoria');
                    if (! is_scalar($classId) || ! isset($filters[$classId]) || isset($categories[$classId]) || ! is_array($category) || count($category) !== 1
                        || ! in_array((string) array_key_first($category), array_map('strval', $filters[$classId]), true)) {
                        throw new RuntimeException('Unexpected or duplicate census category.');
                    }
                    $categories[(int) $classId] = (int) array_key_first($category);
                }
                ksort($categories);
                $key = implode(':', $categories);
                if (isset($mapped[$id][$key])) {
                    throw new RuntimeException('Duplicate census result.');
                }
                $series = data_get($result, 'series');
                if (! is_array($series) || ! array_is_list($series) || $series === []) {
                    throw new RuntimeException('Empty or invalid census coverage.');
                }
                $values = [];
                foreach ($series as $item) {
                    $code = data_get($item, 'localidade.id');
                    $data = data_get($item, 'serie');
                    if (! is_string($code) || ! preg_match('/^[0-9]{7}$/D', $code) || ! isset($municipalities[$code]) || array_key_exists($code, $values)
                        || ($codes !== null && ! in_array((int) $code, array_map('intval', $codes), true))
                        || data_get($item, 'localidade.nivel.id') !== 'N6' || ! is_array($data) || array_keys($data) !== [$year] || ! is_string($data[$year])) {
                        throw new RuntimeException('Invalid census locality, period or source value.');
                    }
                    $values[$code] = $data[$year];
                }
                ksort($values);
                $mapped[$id][$key] = ['categories' => $categories, 'values' => $values];
            }
        }

        return $mapped;
    }

    public function metrics(): array
    {
        return ['requests' => $this->requests, 'attempts' => $this->attempts, 'retries' => $this->attempts - $this->requests];
    }
}
