<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class ApiIpRateLimit implements FilterInterface
{
    use ResponseTrait;

    protected ResponseInterface $response;

    public function before(RequestInterface $request, $arguments = null)
    {
        $this->response = service('response')->setHeader('Cache-Control', 'no-store');

        try {
            $throttler = service('throttler');
            if (! $throttler->check('api-ip-' . hash('sha256', $request->getIPAddress()), 120, 60)) {
                $this->response->setHeader('Retry-After', (string) max(1, $throttler->getTokenTime()));

                return $this->failTooManyRequests('Too many requests.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'API IP rate limit failed: {type}', ['type' => $exception::class]);

            return $this->failServerError('The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
