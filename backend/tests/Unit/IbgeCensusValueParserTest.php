<?php

namespace Tests\Unit;

use App\Support\IbgeCensusValueParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IbgeCensusValueParserTest extends TestCase
{
    #[DataProvider('validValues')]
    public function test_preserves_values_and_markers(string $raw, string $measure, ?string $value, string $status): void
    {
        $this->assertSame(['value' => $value, 'status' => $status, 'source_value' => $raw], (new IbgeCensusValueParser)->parse($raw, $measure));
    }

    public static function validValues(): array
    {
        return [
            ['38', 'number', '38', 'available'], ['85.20', 'number', '85.20', 'available'],
            ['-', 'population', '0', 'available'], ['0', 'population', '0', 'available'], ['0.00', 'percentage', '0', 'available'],
            ['...', 'number', null, 'unavailable'], ['..', 'number', null, 'not_applicable'], ['X', 'population', null, 'suppressed'],
            ['123.45', 'number', '123.45', 'available'], ['100.000000', 'percentage', '100.000000', 'available'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_input(mixed $raw, string $measure): void
    {
        $this->expectException(RuntimeException::class);
        (new IbgeCensusValueParser)->parse($raw, $measure);
    }

    public static function invalidValues(): array
    {
        return [['?', 'number'], [null, 'number'], [1.5, 'number'], ['1.5', 'population'], ['-1', 'population'], ['100.000001', 'percentage'], ['101', 'percentage'], ['1.1234567', 'number'], ['2147483648', 'population'], ['1234567890123456789', 'number'], ['1e2', 'number']];
    }
}
