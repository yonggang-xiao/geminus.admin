<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Geminus\Admin\Libraries\TokenPermissions;
use Throwable;

class ApiAccess implements FilterInterface
{
    use ResponseTrait;

    protected ResponseInterface $response;

    public function before(RequestInterface $request, $arguments = null)
    {
        $authenticator = auth('tokens')->getAuthenticator();
        auth('session');
        $authenticator->logout();
        $this->response = service('response')->setHeader('Cache-Control', 'no-store');

        try {
            $rawToken = preg_match('/\ABearer ([^\s]+)\z/i', $request->getHeaderLine('Authorization'), $matches) ? $matches[1] : '';
            if (! $authenticator->attempt(['token' => $rawToken])->isOK()) {
                return $this->failUnauthorized('A valid access token is required.');
            }

            $user     = $authenticator->getUser();
            $identity = $user->currentAccessToken();
            Services::resetSingle('settings');
            $permission = $arguments[0] ?? '';
            if (! (new TokenPermissions())->allows($user, setting('AuthGroups.permissions'), $identity->scopes, $permission) || ! $user->tokenCan($permission)) {
                return $this->failForbidden('The account and token must both allow this operation.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'API access failed: {type}', ['type' => $exception::class]);

            return $this->failServerError('The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store');
    }
}
