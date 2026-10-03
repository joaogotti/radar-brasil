<?php

namespace Tests\Feature;

use App\Services\InepEducationArchive;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class InepEducationArchiveTest extends TestCase
{
    private function payload(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'inep-zip-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('../source.xlsx', 'fixture');
        $zip->addFromString('md5.txt', md5('fixture').' *source.xlsx');
        $zip->close();
        $body = file_get_contents($path);
        unlink($path);

        return $body;
    }

    public function test_retry_after_integrity_metrics_and_cleanup(): void
    {
        Http::preventStrayRequests();
        $body = $this->payload();
        $calls = 0;
        Http::fake(function () use (&$calls, $body) {
            $calls++;
            if ($calls === 1) {
                return Http::response('', 429, ['Retry-After' => '3']);
            }
            if ($calls === 2) {
                return Http::response('', 503);
            }

            return Http::response($body, 200, ['Content-Length' => strlen($body)]);
        });
        $archive = new class extends InepEducationArchive
        {
            public array $waits = [];

            protected function pause(int $seconds): void
            {
                $this->waits[] = $seconds;
            }
        };
        $extracted = null;
        $result = $archive->read('https://download.inep.gov.br/test.zip', function ($path) use (&$extracted) {
            $extracted = $path;
            $this->assertSame('fixture', file_get_contents($path));
            $this->assertSame('source.xlsx', basename($path));

            return ['parsed'];
        });
        $this->assertSame([3, 2], $archive->waits);
        $this->assertSame(3, $result['artifact']['requests']);
        $this->assertSame(2, $result['artifact']['retries']);
        $this->assertSame(hash('sha256', 'fixture'), $result['artifact']['xlsx_sha256']);
        $this->assertFileDoesNotExist($extracted);
        $this->assertDirectoryDoesNotExist(dirname($extracted));
    }

    public static function failures(): array
    {
        return [['zip'], ['partial'], ['404'], ['retries'], ['retry-after'], ['checksum'], ['parser']];
    }

    #[DataProvider('failures')]
    public function test_failures_do_not_reach_publication_and_clean_downloads(string $case): void
    {
        Http::preventStrayRequests();
        $body = $case === 'zip' ? 'not zip' : $this->payload();
        if ($case === 'checksum') {
            $path = tempnam(sys_get_temp_dir(), 'inep-bad-md5-');
            file_put_contents($path, $body);
            $zip = new ZipArchive;
            $zip->open($path);
            $zip->addFromString('md5.txt', str_repeat('0', 32).' *source.xlsx');
            $zip->close();
            $body = file_get_contents($path);
            unlink($path);
        }
        $sink = null;
        Http::fake(function ($request, $options) use ($case, $body, &$sink) {
            $sink = $options['sink'];

            return match ($case) {
                '404' => Http::response('', 404),
                'retries' => Http::response('', 500),
                'retry-after' => Http::response('', 429, ['Retry-After' => '121']),
                default => Http::response($body, 200, ['Content-Length' => strlen($body) + ($case === 'partial' ? 1 : 0)]),
            };
        });
        $archive = new class extends InepEducationArchive
        {
            protected function pause(int $seconds): void {}
        };
        try {
            $archive->read('https://download.inep.gov.br/test.zip', function () use ($case) {
                $this->assertSame('parser', $case);
                throw new RuntimeException('Invalid XLSX');
            });
            $this->fail('Expected failure.');
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        Http::assertSentCount($case === 'retries' ? 3 : 1);
        $this->assertDirectoryDoesNotExist(dirname($sink));
    }
}
