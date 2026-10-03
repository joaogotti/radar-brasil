<?php

namespace App\Console\Commands;

use App\Services\EducationIndicatorSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncSchoolCensusIndicators extends Command
{
    protected $signature = 'indicators:sync-school-census {--year=2025}';

    protected $description = 'Importa os três totais municipais da Sinopse INEP 2025';

    public function handle(EducationIndicatorSyncService $service): int
    {
        try {
            if ($this->option('year') !== '2025') {
                throw new \RuntimeException('Only --year=2025 is supported.');
            }
            $this->info(json_encode($service->sync('census', 2025, progress: fn ($message) => $this->line($message)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
