<?php

namespace App\Services;

use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

class InepEducationReader
{
    public function census(string $path, array $municipalities): array
    {
        $definitions = array_filter(config('education.indicators'), fn ($d) => isset($d['table']));
        $reader = new Reader;
        $result = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($definitions as $code => $definition) {
                    if ($sheet->getName() !== 'Educação Básica '.$definition['table']) {
                        continue;
                    }
                    if (isset($result[$code])) {
                        throw new RuntimeException('Duplicate census sheet.');
                    }
                    $title = false;
                    $municipalityColumn = null;
                    $totalColumn = null;
                    $rows = [];
                    foreach ($sheet->getRowIterator() as $row) {
                        $cells = $row->toArray();
                        $text = implode(' ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $cells));
                        if (str_contains($text, $definition['table'].' – Número de '.$definition['measure'].' da Educação Básica') && preg_match('/- 2025\s*$/u', $text)) {
                            $title = true;
                        }
                        $normalized = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $cells);
                        $column = array_search('Código do Município', $normalized, true);
                        if ($column !== false) {
                            if ($municipalityColumn !== null) {
                                throw new RuntimeException('Duplicate census header.');
                            }
                            $municipalityColumn = $column;

                            continue;
                        }
                        if ($municipalityColumn === null) {
                            continue;
                        }
                        if ($totalColumn === null && in_array('Total', $normalized, true)) {
                            $totalColumn = array_search('Total', $normalized, true);
                            if ($totalColumn !== $municipalityColumn + 1) {
                                throw new RuntimeException('Unexpected census Total layout.');
                            }

                            continue;
                        }
                        $rawCode = $cells[$municipalityColumn] ?? null;
                        if ($rawCode === null || (is_string($rawCode) && trim($rawCode) === '')) {
                            continue;
                        }
                        if (! $title || $totalColumn === null) {
                            throw new RuntimeException('Missing census title, year or Total header.');
                        }
                        $ibge = $this->municipality($rawCode, $municipalities);
                        if (isset($rows[$ibge])) {
                            throw new RuntimeException('Duplicate census municipality '.$ibge);
                        }
                        $rows[$ibge][2025] = $this->value($cells[$totalColumn] ?? null, false);
                    }
                    if (! $title || count($rows) !== config('education.census_rows')) {
                        throw new RuntimeException('Invalid census title/year or municipality coverage: '.$code.' ('.count($rows).').');
                    }
                    $result[$code] = $rows;
                }
            }
            if (count($result) !== 3) {
                throw new RuntimeException('Missing census sheets.');
            }
            $sets = array_map(fn ($rows) => array_keys($rows), $result);
            foreach ($sets as $set) {
                if (array_diff(reset($sets), $set) !== []) {
                    throw new RuntimeException('Census sheets have different municipalities.');
                }
            }

            return $result;
        } finally {
            $reader->close();
        }
    }

    public function ideb(string $path, array $definition, array $municipalities): array
    {
        $reader = new Reader;
        $result = null;
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== $definition['sheet']) {
                    continue;
                }
                if ($result !== null) {
                    throw new RuntimeException('Duplicate IDEB sheet.');
                }
                $result = [];
                $headers = null;
                $title = false;
                $edition = false;
                $seen = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->toArray();
                    $text = implode(' ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $cells));
                    $title = $title || str_contains($text, 'Ensino Fundamental Regular - '.$definition['stage']);
                    $edition = $edition || (! in_array('CO_MUNICIPIO', $cells, true) && str_contains($text, '2005') && str_contains($text, '2025'));
                    if (in_array('CO_MUNICIPIO', $cells, true)) {
                        if ($headers !== null || ! $title || ! $edition) {
                            throw new RuntimeException('Invalid IDEB title, edition or duplicate header.');
                        }
                        $headers = [];
                        foreach ($cells as $i => $cell) {
                            if (! is_string($cell) || $cell === '') {
                                continue;
                            }
                            if (isset($headers[$cell])) {
                                throw new RuntimeException('Duplicate IDEB column '.$cell);
                            }
                            $headers[$cell] = $i;
                        }
                        $required = ['SG_UF', 'CO_MUNICIPIO', 'NO_MUNICIPIO', 'REDE', ...array_map(fn ($y) => 'VL_OBSERVADO_'.$y, config('education.years'))];
                        if (array_diff($required, array_keys($headers)) !== []) {
                            throw new RuntimeException('Missing IDEB columns.');
                        }
                        $observed = array_filter(array_keys($headers), fn ($h) => str_starts_with($h, 'VL_OBSERVADO_'));
                        if (count($observed) !== count(config('education.years'))) {
                            throw new RuntimeException('Unexpected IDEB years.');
                        }

                        continue;
                    }
                    if ($headers === null) {
                        continue;
                    }
                    $rawCode = $cells[$headers['CO_MUNICIPIO']] ?? null;
                    if ($rawCode === null || (is_string($rawCode) && trim($rawCode) === '')) {
                        continue;
                    }
                    $ibge = $this->municipality($rawCode, $municipalities);
                    $network = $cells[$headers['REDE']] ?? null;
                    if (! in_array($network, ['Pública', 'Municipal', 'Estadual', 'Federal'], true)) {
                        throw new RuntimeException('Unexpected IDEB network.');
                    }
                    if (isset($seen[$ibge][$network])) {
                        throw new RuntimeException('Duplicate IDEB municipality/network.');
                    }
                    $seen[$ibge][$network] = true;
                    if ($network !== 'Pública') {
                        continue;
                    }
                    foreach (config('education.years') as $year) {
                        $result[$ibge][$year] = $this->value($cells[$headers['VL_OBSERVADO_'.$year]] ?? null, true);
                    }
                }
                if ($headers === null || count($result) !== $definition['rows']) {
                    throw new RuntimeException('Invalid IDEB public coverage: '.count($result).'.');
                }
            }
            if ($result === null) {
                throw new RuntimeException('Missing IDEB sheet.');
            }

            return $result;
        } finally {
            $reader->close();
        }
    }

    private function municipality(mixed $raw, array $municipalities): string
    {
        if ((! is_int($raw) && ! is_string($raw)) || ! preg_match('/^[1-9][0-9]{6}$/D', (string) $raw) || ! isset($municipalities[$raw])) {
            throw new RuntimeException('Invalid or unknown INEP municipality: '.(is_scalar($raw) ? $raw : get_debug_type($raw)));
        }

        return (string) $raw;
    }

    private function value(mixed $raw, bool $ideb): array
    {
        if ($ideb && in_array($raw, ['-', 'ND', 'ND*', 'ND***'], true)) {
            return ['value' => null, 'status' => 'unavailable', 'source_value' => $raw];
        }
        if (! is_int($raw) && ! is_float($raw) && ! is_string($raw)) {
            throw new RuntimeException('Invalid INEP value type.');
        }
        $text = (string) $raw;
        $pattern = $ideb ? '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?$/D' : '/^(?:0|[1-9][0-9]{0,17})$/D';
        if (! preg_match($pattern, $text) || ($ideb && (float) $text > 10)) {
            throw new RuntimeException('Invalid INEP value or unknown marker: '.$text);
        }

        return ['value' => $text, 'status' => 'available', 'source_value' => $text];
    }
}
