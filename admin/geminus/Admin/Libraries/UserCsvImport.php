<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use InvalidArgumentException;

class UserCsvImport
{
    /**
     * @param resource $stream
     *
     * @return list<array{row: int, email: string, result: string, reason: string}>
     */
    public function import($stream): array
    {
        $header = fgetcsv($stream, escape: '');
        if (! is_array($header)) {
            throw new InvalidArgumentException('CSV header must be username,email.');
        }

        $header[0] = isset($header[0]) ? preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) : null;
        if ($header !== ['username', 'email']) {
            throw new InvalidArgumentException('CSV header must be username,email.');
        }

        $rows = [];
        $row  = 1;

        while (($fields = fgetcsv($stream, escape: '')) !== false) {
            $row++;
            if ($fields !== [null]) {
                $rows[] = ['number' => $row, 'fields' => $fields];
            }

            if (count($rows) > 500) {
                throw new InvalidArgumentException('CSV must contain at most 500 data rows.');
            }
        }

        $provisioning = new UserProvisioning();
        $report       = [];
        $seen         = [];

        foreach ($rows as $record) {
            $fields   = $record['fields'];
            $username = trim($fields[0] ?? '');
            $email    = strtolower(trim($fields[1] ?? ''));
            $entry    = ['row' => $record['number'], 'email' => $email, 'result' => 'error', 'reason' => ''];

            if (count($fields) !== 2) {
                $entry['reason'] = 'invalid';
            } elseif (isset($seen[$email])) {
                $entry['result'] = 'skipped';
                $entry['reason'] = 'duplicate';
            } else {
                $entry['reason'] = $provisioning->create($username, $email);
                if ($entry['reason'] === 'created') {
                    $entry['result'] = 'created';
                    $entry['reason'] = '';
                    $seen[$email]    = true;
                } elseif ($entry['reason'] === 'duplicate') {
                    $entry['result'] = 'skipped';
                }
            }

            $report[] = $entry;
        }

        return $report;
    }
}
