<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class MicrosoftSettings extends BaseController
{
    public function index(): ResponseInterface|string
    {
        if (! auth()->user()?->can('admin.settings')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Geminus\Admin\Views\settings_microsoft', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.microsoftLogin'),
            'microsoft'  => service('settings')->getMany(['MicrosoftOAuth.tenant', 'MicrosoftOAuth.clientId']),
        ]);
    }

    public function update(): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('admin.settings')) {
            return $this->response->setStatusCode(403);
        }

        $validation = service('validation');
        $validation->setRules([
            'tenant'   => ['label' => 'Admin.microsoftTenant', 'rules' => 'required|regex_match[/^[a-fA-F0-9]{8}(-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12}$/]'],
            'clientId' => ['label' => 'Admin.microsoftClientId', 'rules' => 'required|regex_match[/^[a-fA-F0-9]{8}(-[a-fA-F0-9]{4}){3}-[a-fA-F0-9]{12}$/]'],
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/settings/microsoft'))->withInput()->with('microsoft_errors', $validation->getErrors());
        }

        $data = $validation->getValidated();
        service('settings')->setMany([
            'MicrosoftOAuth.tenant'   => $data['tenant'],
            'MicrosoftOAuth.clientId' => $data['clientId'],
        ]);

        return redirect()->to(route_to('admin/settings/microsoft'))->with('alert', ['type' => 'success', 'message' => lang('Admin.microsoftSettingsSaved')]);
    }
}
