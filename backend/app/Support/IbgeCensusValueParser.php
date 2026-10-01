<?php

namespace App\Support;

use RuntimeException;

class IbgeCensusValueParser
{
    public function parse(mixed $raw, string $measure = 'number'): array
    {
        if (! in_array($measure, ['number', 'population', 'percentage'], true)) {
            throw new RuntimeException('Unknown census measure.');
        }
        $status = match ($raw) {
            '...' => 'unavailable', '..' => 'not_applicable', 'X' => 'suppressed', default => 'available',
        };
        if ($status !== 'available') {
            return ['value' => null, 'status' => $status, 'source_value' => $raw];
        }
        $value = $raw === '-' ? '0' : $raw;
        if (! is_string($value) || ! preg_match('/^[0-9]{1,18}(?:\.[0-9]{1,6})?$/D', $value)) {
            throw new RuntimeException('Invalid census value or unsupported precision.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        if ($measure === 'population' && (! ctype_digit($value) || strlen($whole) > 10 || (strlen($whole) === 10 && strcmp($whole, '2147483647') > 0))) {
            throw new RuntimeException('Population must be a non-negative PostgreSQL integer.');
        }
        if ($measure === 'percentage' && (strlen($whole) > 3 || (int) $whole > 100 || ($whole === '100' && trim($fraction, '0') !== ''))) {
            throw new RuntimeException('Percentage must be between 0 and 100.');
        }

        return ['value' => $whole === '0' && trim($fraction, '0') === '' ? '0' : $value, 'status' => $status, 'source_value' => $raw];
    }
}
