<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\HTTP\ApiResponse;
use Throwable;

class ApiRouteGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $response = service('response');

        try {
            foreach (service('router')->getFilters() as $filter) {
                if (is_string($filter) && preg_match('/\Aapi:[^\s,:*]+\z/', $filter)) {
                    return;
                }
            }

            return ApiResponse::error($response, 403, 'forbidden', 'The API route is not enabled.');
        } catch (Throwable $exception) {
            log_message('error', 'API route guard failed: {type}', ['type' => $exception::class]);

            return ApiResponse::error($response, 500, 'internal_error', 'The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
