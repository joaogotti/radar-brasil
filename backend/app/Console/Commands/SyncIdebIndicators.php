<?php

namespace App\Console\Commands;

use App\Services\EducationIndicatorSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncIdebIndicators extends Command
{
    protected $signature = 'indicators:sync-ideb {--edition=2025} {--network=public}';

    protected $description = 'Importa o IDEB municipal da rede pública, série 2005–2025';

    public function handle(EducationIndicatorSyncService $service): int
    {
        try {
            if ($this->option('edition') !== '2025') {
                throw new \RuntimeException('Only --edition=2025 is supported.');
            }
            $this->info(json_encode($service->sync('ideb', 2025, $this->option('network'), fn ($message) => $this->line($message)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
