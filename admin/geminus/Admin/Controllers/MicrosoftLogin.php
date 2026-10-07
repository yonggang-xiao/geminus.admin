<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Models\LoginModel;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Geminus\Admin\Config\MicrosoftOAuth;
use Geminus\Admin\Libraries\MicrosoftLinks;
use Geminus\Admin\Libraries\OrganizationAzure;
use RuntimeException;
use Throwable;

class MicrosoftLogin extends BaseController
{
    public function start(): RedirectResponse
    {
        if (auth()->loggedIn()) {
            return redirect()->to(route_to('admin/profile'));
        }

        return $this->authorize('login');
    }

    public function connect(): RedirectResponse
    {
        $user = auth()->user();
        if (! $user) {
            return redirect()->to(route_to('login'));
        }

        if ($user->getIdentity(MicrosoftLinks::IDENTITY_TYPE)) {
            return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'success', 'message' => lang('Admin.microsoftLinked')]);
        }

        $password = $this->request->getPost('current_password');
        $hash     = $user->getPasswordHash();
        if (! $hash || ! is_string($password) || ! service('passwords')->verify($password, $hash)) {
            return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.microsoftPasswordInvalid')]);
        }

        if (! (new MicrosoftLinks())->isEligible($user)) {
            return redirect()->to(route_to('admin/profile'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.microsoftLoginFailed')]);
        }

        return $this->authorize('bind', (int) $user->id);
    }

    public function callback(): RedirectResponse
    {
        $flow = session('microsoft_flow');
        session()->remove('microsoft_flow');
        $locale = is_array($flow) && in_array($flow['locale'] ?? null, config('App')->supportedLocales, true)
            ? $flow['locale'] : config('App')->defaultLocale;
        $this->request->setLocale($locale);
        service('language')->setLocale($locale);
        $state = $this->request->getGet('state');
        $code  = $this->request->getGet('code');

        if (! is_array($flow) || ! is_string($state) || ! hash_equals($flow['state'] ?? '', $state)
                              || ! is_string($code) || $code === '' || ($flow['expires'] ?? 0) < time()
                              || ! service('settings')->get('MicrosoftOAuth.enabled')) {
            return $this->failed();
        }

        if (($flow['mode'] ?? null) === 'bind') {
            if (! auth()->loggedIn() || (int) auth()->user()->id !== ($flow['user_id'] ?? null)
                                     || ! (new MicrosoftLinks())->isEligible(auth()->user())) {
                return $this->failed();
            }
        } elseif (($flow['mode'] ?? null) !== 'login' || auth()->loggedIn()) {
            return $this->failed();
        }

        try {
            $provider = $this->provider();
            $token    = $provider->getAccessToken('authorization_code', ['code' => $code]);
            $claims   = $token->getIdTokenClaims();
            if (! is_array($claims) || ! isset($claims['tid'], $claims['oid'], $claims['nonce'])
                                    || ! hash_equals($flow['nonce'], $claims['nonce'])) {
                return $this->failed();
            }

            $tenant = $claims['tid'];
            $object = $claims['oid'];
            $links  = new MicrosoftLinks();

            if ($flow['mode'] === 'bind') {
                return $links->bind(auth()->user(), $tenant, $object)
                    ? redirect()->to(site_url($locale . '/admin/profile'))->with('alert', ['type' => 'success', 'message' => lang('Admin.microsoftLinked')])
                    : $this->failed();
            }

            $user = $links->findUser($tenant, $object);
            if ($user) {
                if (! $links->isEligible($user)) {
                    return $this->failed();
                }

                $identity = model(UserIdentityModel::class)->getIdentityByType($user, MicrosoftLinks::IDENTITY_TYPE);
                $user->touchIdentity($identity);
                auth('session')->getAuthenticator()->login($user);

                try {
                    model(LoginModel::class)->recordLoginAttempt(MicrosoftLinks::IDENTITY_TYPE, $identity->secret, true, $this->request->getIPAddress(), $this->request->getUserAgent()->getAgentString(), $user->id);
                } catch (Throwable $exception) {
                    log_message('error', 'Microsoft sign-in audit failed: {exception}', ['exception' => $exception]);
                }

                return redirect()->to(site_url($locale . '/admin/dashboard'));
            }

            if (! $links->request($tenant, $object, is_string($claims['preferred_username'] ?? null) ? $claims['preferred_username'] : null)) {
                return redirect()->to(site_url($locale . '/login'))->with('error', lang('Admin.microsoftRequestUnavailable'));
            }

            return redirect()->to(site_url($locale . '/login'))->with('message', lang('Admin.microsoftApprovalPending'));
        } catch (Throwable $exception) {
            log_message('error', 'Microsoft sign-in failed: {exception}', ['exception' => $exception]);

            return $this->failed();
        }
    }

    private function authorize(string $mode, ?int $userId = null): RedirectResponse
    {
        if (! service('settings')->get('MicrosoftOAuth.enabled')) {
            return $this->failed();
        }

        try {
            $provider = $this->provider();
            $nonce    = bin2hex(random_bytes(32));
            $url      = $provider->getAuthorizationUrl(['scope' => 'openid profile email', 'nonce' => $nonce]);
            session()->set('microsoft_flow', [
                'state'   => $provider->getState(),
                'nonce'   => $nonce,
                'mode'    => $mode,
                'locale'  => $this->request->getLocale(),
                'user_id' => $userId,
                'expires' => time() + 600,
            ]);

            return redirect()->to($url);
        } catch (Throwable $exception) {
            log_message('error', 'Microsoft authorization failed: {exception}', ['exception' => $exception]);

            return $this->failed();
        }
    }

    private function provider(): OrganizationAzure
    {
        $settings = service('settings');
        $config   = config('MicrosoftOAuth');
        $clientId = $settings->get('MicrosoftOAuth.clientId');
        $tenant   = $settings->get('MicrosoftOAuth.tenant');
        if (! $clientId || ! $tenant || ! $config->clientSecret) {
            throw new RuntimeException('Microsoft sign-in is not configured.');
        }

        return new OrganizationAzure([
            'clientId'               => $clientId,
            'clientSecret'           => $config->clientSecret,
            'tenant'                 => $tenant,
            'redirectUri'            => site_url(config('App')->defaultLocale . '/microsoft/callback'),
            'defaultEndPointVersion' => MicrosoftOAuth::ENDPOINT_VERSION,
        ]);
    }

    private function failed(): RedirectResponse
    {
        if (auth()->loggedIn()) {
            return redirect()->to(site_url($this->request->getLocale() . '/admin/profile'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.microsoftLoginFailed')]);
        }

        return redirect()->to(site_url($this->request->getLocale() . '/login'))->with('error', lang('Admin.microsoftLoginFailed'));
    }
}
