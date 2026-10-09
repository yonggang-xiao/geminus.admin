<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use Geminus\Admin\Libraries\DataManagement\CsvImport;

class UserCsvImport
{
    private const MAX_ROWS = 500;

    public function __construct(private readonly UserProvisioning $provisioning)
    {
    }

    /**
     * @param resource $stream
     *
     * @return list<array{row: int, email: string, result: string, reason: string}>
     */
    public function import($stream): array
    {
        $provisioning = $this->provisioning;
        $seen         = [];
        $report       = (new CsvImport())->import($stream, ['username', 'email'], static function (array $data) use ($provisioning, &$seen): array {
            $username = trim($data['username']);
            $email    = strtolower(trim($data['email']));
            if (isset($seen[$email])) {
                return ['result' => 'skipped', 'reason' => 'duplicate'];
            }
            $reason = $provisioning->create($username, $email);
            if ($reason === 'created') {
                $seen[$email] = true;

                return ['result' => 'created', 'reason' => ''];
            }

            return ['result' => $reason === 'duplicate' ? 'skipped' : 'error', 'reason' => $reason];
        }, self::MAX_ROWS);

        return array_map(static fn (array $entry): array => [
            'row'    => $entry['row'],
            'email'  => strtolower(trim($entry['data']['email'])),
            'result' => $entry['result'],
            'reason' => $entry['reason'] === 'processing' ? 'save' : $entry['reason'],
        ], $report);
    }
}
