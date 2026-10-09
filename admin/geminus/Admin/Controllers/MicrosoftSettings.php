<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Geminus\Admin\Libraries\MicrosoftLinks;
use Geminus\Admin\Libraries\UserManagementPolicy;

class MicrosoftSettings extends BaseController
{
    public function index(): ResponseInterface|string
    {
        if (! auth()->user()?->can('microsoft-settings.manage')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');

        $canApprove = auth()->user()->can('users.edit');
        $links      = new MicrosoftLinks();
        $policy     = new UserManagementPolicy(auth()->user());
        $identities = $canApprove ? model(UserIdentityModel::class)->where('type', MicrosoftLinks::IDENTITY_TYPE)->findAll() : [];
        $bindings   = [];

        foreach ($identities as $identity) {
            $user = auth()->getProvider()->findById($identity->user_id);
            if ($user && ($policy->canManage($user) || ($user->inGroup('superadmin') && $user->id === auth()->id()))) {
                $bindings[] = ['user' => $user, 'identity' => $identity];
            }
        }

        return view('Geminus\Admin\Views\settings_microsoft', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.microsoftLogin'),
            'microsoft'  => service('settings')->getMany(['MicrosoftOAuth.enabled', 'MicrosoftOAuth.tenant', 'MicrosoftOAuth.clientId']),
            'requests'   => $canApprove ? $links->pending() : [],
            'candidates' => $canApprove ? array_values(array_filter(auth()->getProvider()->findAll(), static fn ($user) => $links->isEligible($user) && $policy->canManage($user))) : [],
            'bindings'   => $bindings,
        ]);
    }

    public function update(): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('microsoft-settings.manage')) {
            return $this->response->setStatusCode(403);
        }

        $validation = service('validation');
        $validation->setRules([
            'enabled'  => ['label' => 'Admin.microsoftEnabled', 'rules' => 'permit_empty|in_list[0,1]'],
            'tenant'   => ['label' => 'Admin.microsoftTenant', 'rules' => 'required|regex_match[/^(organizations|[a-fA-F0-9]{8}(-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12})$/]'],
            'clientId' => ['label' => 'Admin.microsoftClientId', 'rules' => 'required|regex_match[/^[a-fA-F0-9]{8}(-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12}$/]'],
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/settings/microsoft'))->withInput()->with('microsoft_errors', $validation->getErrors());
        }

        $data = $validation->getValidated();
        service('settings')->setMany([
            'MicrosoftOAuth.enabled'  => isset($data['enabled']) && $data['enabled'] === '1',
            'MicrosoftOAuth.tenant'   => $data['tenant'],
            'MicrosoftOAuth.clientId' => $data['clientId'],
        ]);

        return redirect()->to(route_to('admin/settings/microsoft'))->with('alert', ['type' => 'success', 'message' => lang('Admin.microsoftSettingsSaved')]);
    }

    public function approve(int $requestId): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('users.edit')) {
            return $this->response->setStatusCode(403);
        }

        $validation = service('validation');
        $validation->setRules(['user_id' => 'required|is_natural_no_zero']);
        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/settings/microsoft'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.microsoftApprovalFailed')]);
        }

        $user = auth()->getProvider()->findById($validation->getValidated()['user_id']);
        if ($user && ! (new UserManagementPolicy(auth()->user()))->canManage($user)) {
            return $this->response->setStatusCode(404);
        }

        if (! $user || ! (new MicrosoftLinks())->approve($requestId, $user)) {
            return redirect()->to(route_to('admin/settings/microsoft'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.microsoftApprovalFailed')]);
        }

        return redirect()->to(route_to('admin/settings/microsoft'))->with('alert', ['type' => 'success', 'message' => lang('Admin.microsoftApproved')]);
    }

    public function reject(int $requestId): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('users.edit')) {
            return $this->response->setStatusCode(403);
        }

        $rejected = (new MicrosoftLinks())->reject($requestId);

        return redirect()->to(route_to('admin/settings/microsoft'))->with('alert', [
            'type'    => $rejected ? 'success' : 'danger',
            'message' => lang($rejected ? 'Admin.microsoftRejected' : 'Admin.microsoftApprovalFailed'),
        ]);
    }

    public function revoke(int $userId): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('users.edit')) {
            return $this->response->setStatusCode(403);
        }

        $user = auth()->getProvider()->findById($userId);
        if ($user && ! ($user->inGroup('superadmin') && $user->id === auth()->id()) && ! (new UserManagementPolicy(auth()->user()))->canManage($user)) {
            return $this->response->setStatusCode(404);
        }

        $revoked = $user && (new MicrosoftLinks())->revoke($user);

        return redirect()->to(route_to('admin/settings/microsoft'))->with('alert', [
            'type'    => $revoked ? 'success' : 'danger',
            'message' => lang($revoked ? 'Admin.microsoftRevoked' : 'Admin.microsoftApprovalFailed'),
        ]);
    }
}
