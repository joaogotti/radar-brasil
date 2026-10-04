<?php

namespace App\Console\Commands;

use App\Services\CempreIndicatorSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCempreIndicators extends Command
{
    protected $signature = 'indicators:sync-cempre';

    protected $description = 'Sincroniza os cinco indicadores municipais CEMPRE/SIDRA 9509 de 2022–2024, com publicação atômica';

    public function handle(CempreIndicatorSyncService $service): int
    {
        try {
            $result = $service->sync(fn (string $message) => $this->line($message));
            $this->info(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
