<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\Services;
use Geminus\Admin\HTTP\ApiResponse;
use Geminus\Admin\Libraries\TokenPermissions;
use Throwable;

class ApiAccess implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authenticator = auth('tokens')->getAuthenticator();
        auth('session');
        $authenticator->logout();
        $response = service('response');
        $response->setHeader('Cache-Control', 'no-store');

        try {
            if (! preg_match('/\ABearer ([^\s]+)\z/i', $request->getHeaderLine('Authorization'), $matches)) {
                return ApiResponse::error($response, 401, 'unauthenticated', 'A valid access token is required.');
            }

            $identity = (new UserIdentityModel())->getAccessTokenByRawToken($matches[1]);
            $provider = auth()->getProvider()::class;
            $user     = $identity === null ? null : (new $provider())->findById($identity->user_id);
            if ($user === null || $user->isBanned() || ! $authenticator->check(['token' => $matches[1]])->isOK()) {
                return ApiResponse::error($response, 401, 'unauthenticated', 'A valid access token is required.');
            }

            $user->setAccessToken($identity);
            $authenticator->login($user);
            Services::resetSingle('settings');
            $permission = $arguments[0] ?? '';
            if (! (new TokenPermissions())->allows($user, setting('AuthGroups.permissions'), $identity->scopes, $permission) || ! $user->tokenCan($permission)) {
                return ApiResponse::error($response, 403, 'forbidden', 'The account and token must both allow this operation.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'API access failed: {type}', ['type' => $exception::class]);

            return ApiResponse::error($response, 500, 'internal_error', 'The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store');
    }
}
