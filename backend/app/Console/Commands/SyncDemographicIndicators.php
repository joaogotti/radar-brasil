<?php

namespace App\Console\Commands;

use App\Services\DemographicIndicatorSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncDemographicIndicators extends Command
{
    protected $signature = 'indicators:sync-demography';

    protected $description = 'Sincroniza sete indicadores municipais de Demografia e Condições de vida do Censo 2022';

    public function handle(DemographicIndicatorSyncService $service): int
    {
        try {
            $this->info(json_encode($service->sync(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            $this->warn('Indicadores já concluídos foram preservados. Reexecute para atualizar a carga.');

            return self::FAILURE;
        }
    }
}
