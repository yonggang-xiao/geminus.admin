<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\HTTP\ApiResponse;
use stdClass;
use Throwable;

class ApiJsonRequest implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $response = service('response');

        try {
            if (! in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH'], true)) {
                return;
            }
            if (! preg_match('~\Aapplication/json(?:\s*;|\z)~i', $request->getHeaderLine('Content-Type'))) {
                return ApiResponse::error($response, 415, 'unsupported_media_type', 'Content-Type must be application/json.');
            }

            try {
                $payload = $request->getJSON(false, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return ApiResponse::error($response, 400, 'invalid_json', 'The request body must be a JSON object.');
            }
            if (! $payload instanceof stdClass) {
                return ApiResponse::error($response, 400, 'invalid_json', 'The request body must be a JSON object.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'API JSON request failed: {type}', ['type' => $exception::class]);

            return ApiResponse::error($response, 500, 'internal_error', 'The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
