<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        return view('Geminus\Admin\Views\dashboard', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.dashboard'),
        ]);
    }
}
