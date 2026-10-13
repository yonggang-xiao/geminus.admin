<?php

declare(strict_types=1);

namespace Geminus\Admin\HTTP;

use CodeIgniter\HTTP\ResponseInterface;

class ApiResponse
{
    public static function error(ResponseInterface $response, int $status, string $code, string $message, array $fields = []): ResponseInterface
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return $response->setStatusCode($status)->setHeader('Cache-Control', 'no-store')->setJSON(['error' => $error]);
    }
}
