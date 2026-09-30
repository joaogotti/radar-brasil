<?php

namespace App\Console\Commands;

use App\Services\EconomicIndicatorSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncEconomicIndicators extends Command
{
    protected $signature = 'indicators:sync-economy';

    protected $description = 'Sincroniza PIB, PIB per capita e quatro VABs municipais publicados pelo IBGE';

    public function handle(EconomicIndicatorSyncService $service): int
    {
        $started = microtime(true);
        try {
            $result = $service->sync(function (string $code, int $year, array $counts): void {
                $this->line("{$code}/{$year}: {$counts['values']} valores; {$counts['unavailable']} indisponíveis; {$counts['absent']} municípios ausentes da resposta.");
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Falha ao sincronizar economia: '.$exception->getMessage());
            $this->warn('Lotes anteriores concluídos foram preservados. Reexecute o comando para atualizar a série.');
            $this->line(sprintf('%d tentativas HTTP; %.2f s.', $service->requestCount(), microtime(true) - $started));

            return self::FAILURE;
        }
        $this->info(sprintf('Sincronização concluída: %d requisições HTTP; %.2f s.', $result['requests'], microtime(true) - $started));

        return self::SUCCESS;
    }
}
