<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $user = auth()->user();
        if ($this->request->getUri()->getPath() === '/' && in_array($user->language, config('App')->supportedLocales, true)) {
            return redirect()->to(site_url($user->language . '/admin/dashboard'));
        }

        return view('Geminus\Admin\Views\dashboard', [
            'me'         => $user,
            'page_title' => lang('Admin.dashboard'),
        ]);
    }
}
