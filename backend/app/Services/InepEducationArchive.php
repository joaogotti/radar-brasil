<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class InepEducationArchive
{
    public function read(string $url, callable $consume): array
    {
        $directory = sys_get_temp_dir().'/radar-education-'.bin2hex(random_bytes(12));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create INEP temporary directory.');
        }
        $started = microtime(true);
        $requests = 0;
        $archive = new ZipArchive;
        $opened = false;
        try {
            $path = $directory.'/download.zip';
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                file_put_contents($path, '');
                $requests++;
                try {
                    $response = Http::connectTimeout(20)->timeout(600)
                        ->withOptions(['sink' => $path, 'allow_redirects' => false])->get($url);
                    if ($response->successful() && $response->status() === 200) {
                        $length = $response->header('Content-Length');
                        clearstatcache(true, $path);
                        if (filesize($path) === 0 || ($length !== '' && ctype_digit($length) && filesize($path) !== (int) $length)) {
                            throw new RuntimeException('Incomplete INEP download.');
                        }
                        break;
                    }
                    if ($attempt === 3 || ($response->status() !== 429 && ! $response->serverError())) {
                        throw new RuntimeException('INEP download HTTP '.$response->status());
                    }
                    $header = $response->header('Retry-After');
                    $wait = ctype_digit($header) ? (int) $header : max(0, (strtotime($header) ?: time()) - time());
                    if ($wait > 120) {
                        throw new RuntimeException('INEP Retry-After exceeds 120 seconds; retry the command later.');
                    }
                    $this->pause(max($attempt, $wait));
                } catch (ConnectionException $exception) {
                    if ($attempt === 3) {
                        throw $exception;
                    }
                    $this->pause($attempt);
                }
            }
            $downloadSeconds = microtime(true) - $started;
            $integrityStarted = microtime(true);
            if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
                throw new RuntimeException('Invalid INEP ZIP.');
            }
            $opened = true;
            $sheets = [];
            $manifests = [];
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = $archive->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.xlsx') && ! str_starts_with(basename($name), '~$')) {
                    $sheets[] = $name;
                }
                if (str_ends_with(strtolower($name), '.txt') && str_contains(strtolower($name), 'md5')) {
                    $stat = $archive->statIndex($i);
                    if ($stat['size'] > 65536) {
                        throw new RuntimeException('Oversized INEP checksum manifest.');
                    }
                    $manifests[] = $archive->getFromIndex($i);
                }
            }
            if (count($sheets) !== 1 || $manifests === []) {
                throw new RuntimeException('Expected one XLSX and an official MD5 manifest.');
            }
            $name = $sheets[0];
            $stat = $archive->statName($name);
            if ($stat['size'] > 400_000_000) {
                throw new RuntimeException('INEP XLSX exceeds the validated size limit.');
            }
            $input = $archive->getStream($name);
            $xlsx = $directory.'/source.xlsx';
            $output = fopen($xlsx, 'wb');
            if ($input === false || $output === false) {
                if (is_resource($input)) {
                    fclose($input);
                }
                if (is_resource($output)) {
                    fclose($output);
                }
                throw new RuntimeException('Cannot extract INEP XLSX.');
            }
            try {
                $bytes = stream_copy_to_stream($input, $output);
            } finally {
                fclose($input);
                fclose($output);
            }
            if ($bytes !== $stat['size']) {
                throw new RuntimeException('Truncated INEP XLSX.');
            }
            $expected = [];
            foreach ($manifests as $manifest) {
                if (! mb_check_encoding($manifest, 'UTF-8')) {
                    $manifest = mb_convert_encoding($manifest, 'UTF-8', 'Windows-1252');
                }
                foreach (preg_split('/\R/u', $manifest) as $line) {
                    if (str_contains($line, basename($name)) && preg_match('/\b([a-f0-9]{32})\b/i', $line, $match)) {
                        $expected[] = strtolower($match[1]);
                    }
                }
            }
            $md5 = hash_file('md5', $xlsx);
            if (count($expected) !== 1 || ! hash_equals($expected[0], $md5)) {
                throw new RuntimeException('INEP XLSX official checksum mismatch or missing entry.');
            }
            $metadata = ['url' => $url, 'file' => $name, 'download_bytes' => filesize($path), 'xlsx_bytes' => $bytes,
                'zip_sha256' => hash_file('sha256', $path), 'xlsx_sha256' => hash_file('sha256', $xlsx), 'official_md5' => $md5,
                'download_seconds' => round($downloadSeconds, 2), 'integrity_seconds' => round(microtime(true) - $integrityStarted, 2),
                'requests' => $requests, 'retries' => $requests - 1];
            $parseStarted = microtime(true);
            $data = $consume($xlsx);

            return ['data' => $data, 'artifact' => [...$metadata, 'parse_seconds' => round(microtime(true) - $parseStarted, 2)]];
        } finally {
            if ($opened) {
                $archive->close();
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    protected function pause(int $seconds): void
    {
        sleep($seconds);
    }
}
