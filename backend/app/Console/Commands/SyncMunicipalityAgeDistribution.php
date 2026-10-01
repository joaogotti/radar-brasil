<?php

namespace App\Console\Commands;

use App\Services\MunicipalityAgeDistributionSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncMunicipalityAgeDistribution extends Command
{
    protected $signature = 'municipalities:sync-age-distribution';

    protected $description = 'Sincroniza a distribuição municipal por sexo e faixa etária do Censo 2022';

    public function handle(MunicipalityAgeDistributionSyncService $service): int
    {
        try {
            $this->info(json_encode($service->sync(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            $this->warn('A distribuição anterior foi preservada; nenhum lote parcial foi publicado.');

            return self::FAILURE;
        }
    }
}
