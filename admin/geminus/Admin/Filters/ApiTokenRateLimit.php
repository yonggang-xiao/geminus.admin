<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\HTTP\ApiResponse;
use Throwable;

class ApiTokenRateLimit implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $response = service('response');

        try {
            $authenticator = auth('tokens')->getAuthenticator();
            auth('session');
            $token = $authenticator->getUser()?->currentAccessToken();
            if ($token === null) {
                return ApiResponse::error($response, 401, 'unauthenticated', 'A valid access token is required.');
            }
            $throttler = service('throttler');
            if (! $throttler->check('api-token-' . $token->id, 60, 60)) {
                return ApiResponse::error($response->setHeader('Retry-After', (string) max(1, $throttler->getTokenTime())), 429, 'rate_limited', 'Too many requests.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'API token rate limit failed: {type}', ['type' => $exception::class]);

            return ApiResponse::error($response, 500, 'internal_error', 'The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
