<?php

namespace App\Console\Commands;

use App\Services\CensusIndicatorSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCensusIndicators extends Command
{
    protected $signature = 'indicators:sync-census';

    protected $description = 'Sincroniza população, área e densidade de 2022 pelo agregado 4714 do IBGE';

    public function handle(CensusIndicatorSyncService $service): int
    {
        $started = microtime(true);

        try {
            $result = $service->sync();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Falha ao sincronizar indicadores: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result['counts'] as $code => $counts) {
            $this->info("{$code}: {$counts['municipalities']} valores municipais; {$counts['states']} valores estaduais (2022).");
        }
        foreach (['missing_municipalities', 'missing_states'] as $key) {
            if ($result[$key] !== []) {
                $this->warn($key.' — códigos cadastrados sem dados na fonte de 2022: '.implode(', ', $result[$key]));
            }
        }
        $this->info(sprintf('Sincronização concluída em %.2f s.', microtime(true) - $started));

        return self::SUCCESS;
    }
}
