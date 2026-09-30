<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\MunicipalityIndicatorValue;
use App\Models\State;
use App\Services\EconomicIndicatorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EconomicIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $es = State::create(['ibge_code' => 32, 'name' => 'Espírito Santo', 'abbreviation' => 'ES']);
        $sp = State::create(['ibge_code' => 35, 'name' => 'São Paulo', 'abbreviation' => 'SP']);
        $es->municipalities()->create(['ibge_code' => 3205309, 'name' => 'Vitória']);
        $sp->municipalities()->create(['ibge_code' => 3550308, 'name' => 'São Paulo']);
    }

    private function fake(?callable $change = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/economic-aggregates.json')), true, flags: JSON_THROW_ON_ERROR);
        Http::fake(function (Request $request) use ($fixture, $change) {
            $url = $request->url();
            if (preg_match('~/agregados/5938/periodos/(\d{4})/variaveis/(\d+)~', $url, $matches)) {
                $item = collect($fixture)->firstWhere('id', $matches[2]);
                foreach ($item['resultados'][0]['series'] as &$series) {
                    $series['serie'] = [$matches[1] => $series['serie'][2021]];
                }
                $payload = [$item];
            } elseif (preg_match('~/pesquisas/38/periodos/(\d{4})/indicadores/47001/resultados/~', $url, $matches)) {
                $payload = [['id' => 47001, 'res' => [
                    ['localidade' => '320530', 'res' => [$matches[1] => '87520.35'], 'notas' => [$matches[1] => null]],
                    ['localidade' => '355030', 'res' => [$matches[1] => '93156.23'], 'notas' => [$matches[1] => null]],
                ]]];
            } elseif ($url === 'https://servicodados.ibge.gov.br/api/v1/pesquisas/38/indicadores/47001') {
                $payload = [['id' => 47001, 'indicador' => 'Série revisada', 'unidade' => ['id' => 'R$', 'multiplicador' => 1]]];
            } else {
                throw new \RuntimeException('Unexpected URL: '.$url);
            }

            return $change ? $change($url, $payload) : Http::response($payload);
        });
    }

    public function test_imports_six_indicators_with_correct_sources_periods_and_existing_endpoint_contract(): void
    {
        $this->fake();
        $result = app(EconomicIndicatorSyncService::class)->sync();
        $this->assertSame(117, $result['requests']);
        Http::assertSentCount(117);
        $this->assertDatabaseCount('indicators', 6);
        $this->assertDatabaseCount('municipality_indicator_values', 232);
        $this->assertDatabaseCount('state_indicator_values', 0);
        foreach (['municipal_gdp' => 37, 'agriculture_gva' => 513, 'industry_gva' => 517, 'services_gva' => 6575, 'public_administration_gva' => 525] as $code => $variable) {
            $indicator = Indicator::where('code', $code)->firstOrFail();
            $this->assertSame('ibge_aggregates', $indicator->source_type);
            $this->assertSame(5938, $indicator->source_aggregate_id);
            $this->assertSame($variable, $indicator->source_variable_id);
            $this->assertSame('Mil Reais', $indicator->unit);
            $this->assertSame('Referência 2010', $indicator->source_methodological_reference);
            $this->assertNull($indicator->source_research_id);
            $this->assertNull($indicator->source_indicator_id);
            $this->assertSame(2002, $indicator->municipalityValues()->min('reference_year'));
            $this->assertSame($variable === 37 ? 2023 : 2021, $indicator->municipalityValues()->max('reference_year'));
        }
        $research = Indicator::where('code', 'municipal_gdp_per_capita')->firstOrFail();
        $this->assertSame('ibge_research', $research->source_type);
        $this->assertSame(38, $research->source_research_id);
        $this->assertSame(47001, $research->source_indicator_id);
        $this->assertSame('R$', $research->unit);
        $this->assertSame('Série revisada', $research->source_series);
        $this->assertNull($research->source_aggregate_id);
        $this->assertNull($research->source_variable_id);
        $this->assertNull($research->source_methodological_reference);
        $this->assertSame(2010, $research->municipalityValues()->min('reference_year'));
        $this->assertSame(2023, $research->municipalityValues()->max('reference_year'));
        $response = $this->getJson('/api/municipalities/3205309/indicators')->assertOk()->assertJsonCount(116, 'indicators');
        $perCapita = collect($response->json('indicators'))->firstWhere('code', 'municipal_gdp_per_capita');
        $this->assertSame('87520.350000', $perCapita['value']);
        $this->assertSame(2023, $perCapita['reference_year']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/variaveis/37') && $request['localidades'] === 'N6[all]');
    }

    public function test_numeric_values_and_special_symbols_are_distinguished_without_rounding(): void
    {
        $symbols = ['28257606', '-19046', '-', '0', '...', '..', 'X', null];
        $series = [];
        foreach ($symbols as $index => $value) {
            $code = (string) (3201000 + $index * 10);
            State::firstOrFail()->municipalities()->create(['ibge_code' => $code, 'name' => 'Fixture '.$index]);
            $series[] = ['localidade' => ['id' => $code, 'nivel' => ['id' => 'N6']], 'serie' => [2002 => $value]];
        }
        $this->fake(function (string $url, array $payload) use ($series) {
            if (str_contains($url, '/periodos/2002/variaveis/37?')) {
                $payload[0]['resultados'][0]['series'] = $series;
            }

            return Http::response($payload);
        });
        $result = app(EconomicIndicatorSyncService::class)->sync();
        $indicator = Indicator::where('code', 'municipal_gdp')->firstOrFail();
        $values = $indicator->municipalityValues()->where('reference_year', 2002)->orderBy('municipality_id')->pluck('value')->all();
        $this->assertSame(['28257606.000000', '-19046.000000', '0.000000', '0.000000'], $values);
        $this->assertSame(4, $result['counts']['municipal_gdp'][2002]['unavailable']);
    }

    public function test_resync_preserves_identity_and_updates_revisions_and_explicit_unavailability(): void
    {
        $this->fake();
        app(EconomicIndicatorSyncService::class)->sync();
        $identities = MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at', 'value'])->toArray();
        $catalog = Indicator::orderBy('id')->get(['id', 'created_at'])->toArray();
        $this->travel(1)->hours();
        app(EconomicIndicatorSyncService::class)->sync();
        $this->assertSame($identities, MunicipalityIndicatorValue::orderBy('id')->get(['id', 'created_at', 'value'])->toArray());
        $this->assertSame($catalog, Indicator::orderBy('id')->get(['id', 'created_at'])->toArray());
        $this->assertTrue(MunicipalityIndicatorValue::firstOrFail()->updated_at->greaterThan(MunicipalityIndicatorValue::firstOrFail()->created_at));

        $indicator = Indicator::where('code', 'municipal_gdp')->firstOrFail();
        $original = $indicator->municipalityValues()->where('reference_year', 2002)->firstOrFail();
        $this->fake(function (string $url, array $payload) {
            if (str_contains($url, '/periodos/2002/variaveis/37?')) {
                $payload[0]['resultados'][0]['series'][0]['serie'][2002] = '123.456789';
                $payload[0]['resultados'][0]['series'][1]['serie'][2002] = 'X';
            }

            return Http::response($payload);
        });
        app(EconomicIndicatorSyncService::class)->sync();
        $this->assertSame('123.456789', $original->fresh()->value);
        $this->assertTrue($original->created_at->equalTo($original->fresh()->created_at));
        $this->assertDatabaseCount('municipality_indicator_values', 231);
        $this->assertSame(1, $indicator->municipalityValues()->where('reference_year', 2002)->count());
    }

    #[DataProvider('invalidBatches')]
    public function test_invalid_batch_is_rejected_without_partial_writes(string $case): void
    {
        $this->fake(function (string $url, array $payload) use ($case) {
            if (str_contains($url, '/periodos/2002/variaveis/37?')) {
                switch ($case) {
                    case 'id': $payload[0]['id'] = '38';
                        break;
                    case 'unit': $payload[0]['unidade'] = 'Reais';
                        break;
                    case 'period': $payload[0]['resultados'][0]['series'][1]['serie'] = [2023 => '1'];
                        break;
                    case 'unknown': $payload[0]['resultados'][0]['series'][1]['localidade']['id'] = '9999999';
                        break;
                    case 'duplicate': $payload[0]['resultados'][0]['series'][] = $payload[0]['resultados'][0]['series'][0];
                        break;
                    case 'level': $payload[0]['resultados'][0]['series'][1]['localidade']['nivel']['id'] = 'N3';
                        break;
                    case 'precision': $payload[0]['resultados'][0]['series'][1]['serie'][2002] = '1.1234567';
                        break;
                    case 'overflow': $payload[0]['resultados'][0]['series'][1]['serie'][2002] = '1234567890123456789';
                        break;
                    case 'number': $payload[0]['resultados'][0]['series'][1]['serie'][2002] = 1.5;
                        break;
                    case 'text': $payload[0]['resultados'][0]['series'][1]['serie'][2002] = 'invalid';
                        break;
                    case 'classification': $payload[0]['resultados'][0]['classificacoes'] = [['id' => 1]];
                        break;
                    case 'empty': $payload[0]['resultados'][0]['series'] = [];
                        break;
                    case 'json': return Http::response('<html>error</html>');
                }
            }

            return Http::response($payload);
        });
        $this->artisan('indicators:sync-economy')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
        $this->assertDatabaseCount('municipality_indicator_values', 0);
        Http::assertSentCount(2);
    }

    public static function invalidBatches(): array
    {
        return array_map(fn ($case) => [$case], ['id', 'unit', 'period', 'unknown', 'duplicate', 'level', 'precision', 'overflow', 'number', 'text', 'classification', 'empty', 'json']);
    }

    #[DataProvider('invalidResearchBatches')]
    public function test_research_inconsistency_preserves_completed_aggregate_batches(string $case): void
    {
        $this->fake(function (string $url, array $payload) use ($case) {
            if (str_contains($url, '/periodos/2010/indicadores/47001/')) {
                switch ($case) {
                    case 'unknown': $payload[0]['res'][1]['localidade'] = '999999';
                        break;
                    case 'seven_digits': $payload[0]['res'][1]['localidade'] = '3550308';
                        break;
                    case 'duplicate': $payload[0]['res'][] = $payload[0]['res'][0];
                        break;
                    case 'period': $payload[0]['res'][1]['res'] = [2009 => '1'];
                        break;
                    case 'indicator': $payload[0]['id'] = 47002;
                        break;
                }
            }

            return Http::response($payload);
        });
        $this->artisan('indicators:sync-economy')->assertFailed();
        $this->assertDatabaseCount('indicators', 5);
        $this->assertDatabaseCount('municipality_indicator_values', 204);
    }

    public static function invalidResearchBatches(): array
    {
        return array_map(fn ($case) => [$case], ['unknown', 'seven_digits', 'duplicate', 'period', 'indicator']);
    }

    public function test_ambiguous_prefix_is_rejected_before_requesting_or_writing_data(): void
    {
        State::firstOrFail()->municipalities()->create(['ibge_code' => 3205308, 'name' => 'Ambiguous']);
        $this->artisan('indicators:sync-economy')->assertFailed();
        Http::assertNothingSent();
        $this->assertDatabaseCount('indicators', 0);
    }

    public function test_research_unit_and_multiplier_are_validated_before_any_batch(): void
    {
        $this->fake(function (string $url, array $payload) {
            $payload[0]['unidade']['multiplicador'] = 1000;

            return Http::response($payload);
        });
        $this->artisan('indicators:sync-economy')->assertFailed();
        $this->assertDatabaseCount('indicators', 0);
        Http::assertSentCount(1);
    }

    public function test_transient_failures_are_retried_and_successful_batches_are_saved(): void
    {
        $attempts = 0;
        $this->fake(function (string $url, array $payload) use (&$attempts) {
            if (str_contains($url, '/periodos/2002/variaveis/37?') && ++$attempts < 3) {
                return Http::response([], $attempts === 1 ? 429 : 503);
            }

            return Http::response($payload);
        });
        $result = app(EconomicIndicatorSyncService::class)->sync();
        $this->assertSame(119, $result['requests']);
        $this->assertDatabaseCount('municipality_indicator_values', 232);
    }

    #[DataProvider('httpFailures')]
    public function test_http_failures_have_bounded_retries_and_preserve_prior_batches(int $status, int $attempts): void
    {
        $this->fake(fn (string $url, array $payload) => str_contains($url, '/periodos/2003/variaveis/37?')
            ? Http::response([], $status) : Http::response($payload));
        $this->artisan('indicators:sync-economy')->assertFailed();
        Http::assertSentCount(2 + $attempts);
        $this->assertDatabaseCount('municipality_indicator_values', 2);
        $this->assertDatabaseHas('municipality_indicator_values', ['reference_year' => 2002, 'value' => '31423978']);
    }

    public static function httpFailures(): array
    {
        return [[503, 3], [404, 1]];
    }

    public function test_connection_failure_has_only_three_attempts(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $service = app(EconomicIndicatorSyncService::class);
        try {
            $service->sync();
            $this->fail('Connection failure must be reported.');
        } catch (ConnectionException) {
            $this->assertSame(3, $service->requestCount());
        }
        $this->assertDatabaseCount('indicators', 0);
    }
}
