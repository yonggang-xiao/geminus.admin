<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\HTTP\ApiResponse;
use Throwable;

class ApiIpRateLimit implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $response = service('response');

        try {
            $throttler = service('throttler');
            if (! $throttler->check('api-ip-' . hash('sha256', $request->getIPAddress()), 120, 60)) {
                return ApiResponse::error($response->setHeader('Retry-After', (string) max(1, $throttler->getTokenTime())), 429, 'rate_limited', 'Too many requests.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'API IP rate limit failed: {type}', ['type' => $exception::class]);

            return ApiResponse::error($response, 500, 'internal_error', 'The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
