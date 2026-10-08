<?php

declare(strict_types=1);

namespace Modules\Announcements\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Modules\Announcements\Models\AnnouncementModel;

class Announcements extends BaseController
{
    public function index(): string
    {
        $model = new AnnouncementModel();

        return view('Modules\Announcements\Views\index', [
            'me'            => auth()->user(),
            'page_title'    => lang('Announcements.title'),
            'announcements' => $model->orderBy('id', 'DESC')->paginate(15),
            'pager'         => $model->pager,
        ]);
    }

    public function create(): string
    {
        return view('Modules\Announcements\Views\create', [
            'me'         => auth()->user(),
            'page_title' => lang('Announcements.create'),
        ]);
    }

    public function store(): RedirectResponse
    {
        $validation = service('validation');
        $validation->setRules([
            'title' => ['label' => 'Announcements.name', 'rules' => 'required|max_length[150]'],
            'body'  => ['label' => 'Announcements.body', 'rules' => 'required|max_length[2000]'],
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/announcements/create'))->withInput()->with('errors', $validation->getErrors());
        }

        (new AnnouncementModel())->insert($validation->getValidated());

        return redirect()->to(route_to('admin/announcements'))->with('alert', [
            'type' => 'success', 'message' => lang('Announcements.saved'),
        ]);
    }
}
