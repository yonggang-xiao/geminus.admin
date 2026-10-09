<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\DataManagement;

use InvalidArgumentException;
use RuntimeException;

final class Csv
{
    /**
     * @param resource     $stream
     * @param list<string> $columns
     *
     * @return list<array{number: int, fields: array}>
     */
    public function read($stream, array $columns, int $maxRows = 500): array
    {
        $header = fgetcsv($stream, escape: '');
        if (is_array($header)) {
            $header[0] = isset($header[0]) ? preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) : null;
        }
        if ($header !== $columns) {
            throw new InvalidArgumentException('CSV header must be ' . implode(',', $columns) . '.');
        }

        $rows   = [];
        $number = 1;

        while (($fields = fgetcsv($stream, escape: '')) !== false) {
            $number++;
            if ($fields !== [null]) {
                $rows[] = ['number' => $number, 'fields' => $fields];
            }
            if (count($rows) > $maxRows) {
                throw new InvalidArgumentException('CSV must contain at most ' . $maxRows . ' data rows.');
            }
        }

        return $rows;
    }

    /**
     * Spreadsheet-sensitive cells are prefixed with an apostrophe; read() preserves it.
     *
     * @param list<string>           $columns
     * @param iterable<list<string>> $rows
     */
    public function write(array $columns, iterable $rows = [], int $maxRows = 10000): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Could not open CSV stream.');
        }

        try {
            $this->writeRow($stream, $columns);
            $count = 0;

            foreach ($rows as $fields) {
                if (++$count > $maxRows) {
                    throw new InvalidArgumentException('CSV export limit exceeded.');
                }
                if (count($fields) !== count($columns)) {
                    throw new InvalidArgumentException('CSV column count does not match.');
                }
                $this->writeRow($stream, $fields);
            }
            rewind($stream);
            $contents = stream_get_contents($stream);
            if ($contents === false) {
                throw new RuntimeException('Could not read CSV stream.');
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param resource $stream
     */
    private function writeRow($stream, array $fields): void
    {
        $fields = array_map(static fn (string $value): string => preg_match('/^[=+\-@\t\r\n]/', $value) ? "'" . $value : $value, $fields);
        if (fputcsv($stream, $fields, escape: '') === false) {
            throw new RuntimeException('Could not write CSV row.');
        }
    }
}
