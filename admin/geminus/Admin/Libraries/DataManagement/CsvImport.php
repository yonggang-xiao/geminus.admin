<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\DataManagement;

use Config\Services;
use InvalidArgumentException;
use Throwable;

final class CsvImport
{
    public function import($stream, array $columns, callable $process, int $maxRows = 500, array $rules = []): array
    {
        if ($columns === [] || count(array_unique($columns)) !== count($columns)) {
            throw new InvalidArgumentException('Import columns must be nonempty and unique.');
        }
        $rows   = (new Csv())->read($stream, $columns, $maxRows);
        $report = [];

        foreach ($rows as $record) {
            $data = [];

            foreach ($columns as $index => $column) {
                $data[$column] = $record['fields'][$index] ?? '';
            }
            $entry = ['row' => $record['number'], 'data' => $data, 'result' => 'error', 'reason' => '', 'errors' => []];
            if (count($record['fields']) !== count($columns)) {
                $entry['reason'] = 'invalid';
            } else {
                $validation = Services::validation(null, false);
                $validation->setRules($rules);
                if ($rules !== [] && ! $validation->run($data)) {
                    $entry['reason'] = 'invalid';
                    $entry['errors'] = $validation->getErrors();
                } else {
                    try {
                        $outcome = $process($rules === [] ? $data : $validation->getValidated());
                        if (! is_array($outcome) || ! in_array($outcome['result'] ?? null, ['created', 'skipped', 'error'], true) || ! is_string($outcome['reason'] ?? null)) {
                            throw new InvalidArgumentException('Invalid import row outcome.');
                        }
                        $entry['result'] = $outcome['result'];
                        $entry['reason'] = $outcome['reason'];
                    } catch (Throwable $exception) {
                        $entry['reason'] = 'processing';
                        log_message('error', 'CSV row processing failed: {type}', ['type' => $exception::class]);
                    }
                }
            }
            $report[] = $entry;
        }

        return $report;
    }
}
