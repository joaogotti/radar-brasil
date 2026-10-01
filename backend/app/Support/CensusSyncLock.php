<?php

namespace App\Support;

use RuntimeException;

class CensusSyncLock
{
    public function run(string $family, callable $callback): mixed
    {
        $handle = fopen(storage_path('app/census-'.$family.'-'.config('demography.year').'.lock'), 'c');
        if ($handle === false) {
            throw new RuntimeException('Cannot open census synchronization lock.');
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('A synchronization for this family and period is already running.');
        }
        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
