<?php

namespace App\Console\Commands;

use App\Services\MunicipalitySyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncMunicipalities extends Command
{
    protected $signature = 'municipalities:sync';

    protected $description = 'Sincroniza os municípios com a API oficial do IBGE';

    public function handle(MunicipalitySyncService $service): int
    {
        try {
            $count = $service->sync();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Falha ao sincronizar municípios: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Municípios sincronizados: {$count}.");

        return self::SUCCESS;
    }
}
