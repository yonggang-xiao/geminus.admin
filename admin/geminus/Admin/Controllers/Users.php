<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Geminus\Admin\Libraries\UserCsvImport;
use Geminus\Admin\Libraries\UserProvisioning;
use InvalidArgumentException;

class Users extends BaseController
{
    public function index(): ResponseInterface|string
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');
        $users = $this->filteredUsers();

        return view('Geminus\Admin\Views\users', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.users'),
            'users'      => $users->withIdentities()->paginate(20),
            'pager'      => $users->pager,
            'search'     => trim((string) $this->request->getGet('q')),
            'sort'       => $this->sort(),
            'direction'  => $this->direction(),
            'report'     => session('user_import_report'),
        ]);
    }

    public function edit(int $userId): ResponseInterface|string
    {
        $user = $this->editableUser($userId);
        if ($user === null) {
            return $this->response->setStatusCode(404);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Geminus\Admin\Views\user_edit', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.editUser'),
            'user'       => $user,
            'role'       => $user->inGroup('admin') ? 'admin' : 'user',
        ]);
    }

    public function create(): ResponseInterface|string
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Geminus\Admin\Views\user_create', ['me' => auth()->user(), 'page_title' => lang('Admin.createUser')]);
    }

    public function store(): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        $validation = service('validation');
        $validation->setRules([
            'username' => config('Auth')->usernameValidationRules,
            'email'    => config('Auth')->emailValidationRules,
        ]);
        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/users/create'))->withInput()->with('user_errors', $validation->getErrors());
        }

        $data   = $validation->getValidated();
        $result = (new UserProvisioning())->create($data['username'], $data['email']);
        if ($result !== 'created') {
            $field = in_array($result, ['username', 'invalid'], true) ? 'username' : 'email';

            return redirect()->to(route_to('admin/users/create'))->withInput()->with('user_errors', [$field => lang('Admin.userReason_' . $result)]);
        }

        return redirect()->to(route_to('admin/users'))->with('alert', ['type' => 'success', 'message' => lang('Admin.userCreatedSuccess')]);
    }

    public function update(int $userId): RedirectResponse|ResponseInterface
    {
        $user = $this->editableUser($userId);
        if ($user === null) {
            return $this->response->setStatusCode(404);
        }

        $validation = service('validation');
        $validation->setRules([
            'role'   => 'required|in_list[user,admin]',
            'status' => 'required|in_list[enabled,banned]',
        ]);
        if (! $validation->run($this->request->getPost())) {
            return redirect()->to(route_to('admin/users/edit', $userId))->withInput()->with('user_errors', $validation->getErrors());
        }

        $data = $validation->getValidated();
        $user->syncGroups($data['role']);
        if ($data['status'] === 'banned' && ! $user->isBanned()) {
            $user->ban();
        } elseif ($data['status'] === 'enabled' && $user->isBanned()) {
            $user->unBan();
        }

        return redirect()->to(route_to('admin/users'))->with('alert', ['type' => 'success', 'message' => lang('Admin.userSaved')]);
    }

    public function template(): ResponseInterface
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        return $this->csvResponse('users-template.csv', "username,email\r\n");
    }

    public function export(): ResponseInterface
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        $users = $this->filteredUsers()->withIdentities()->findAll(10001);
        if (count($users) > 10000) {
            return $this->response->setStatusCode(413)->setBody(lang('Admin.exportLimit'));
        }

        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, ['username', 'email']);

        foreach ($users as $user) {
            fputcsv($stream, [$this->csvValue((string) $user->username), $this->csvValue((string) $user->email)]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $this->csvResponse('users.csv', $csv);
    }

    public function import(): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid() || $file->getSize() > 1024 * 1024 || strtolower($file->getClientExtension()) !== 'csv' || ! in_array($file->getMimeType(), ['text/plain', 'text/csv', 'application/vnd.ms-excel'], true)) {
            return redirect()->to(route_to('admin/users'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.invalidUserCsv')]);
        }

        $stream = fopen($file->getTempName(), 'rb');

        try {
            $report = (new UserCsvImport())->import($stream);
        } catch (InvalidArgumentException $exception) {
            return redirect()->to(route_to('admin/users'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.invalidUserCsv')]);
        } finally {
            fclose($stream);
        }

        return redirect()->to(route_to('admin/users'))
            ->with('user_import_report', $report)
            ->with('alert', ['type' => 'success', 'message' => lang('Admin.importFinished')]);
    }

    private function filteredUsers(): UserModel
    {
        $users      = model(get_class(auth()->getProvider()), false);
        $search     = trim((string) $this->request->getGet('q'));
        $userTable  = config('Auth')->tables['users'];
        $identities = config('Auth')->tables['identities'];

        if ($search !== '') {
            $search = mb_substr($search, 0, 100);
            $users->select($userTable . '.*')->join($identities, $identities . '.user_id = ' . $userTable . '.id AND ' . $identities . ".type = '" . Session::ID_TYPE_EMAIL_PASSWORD . "'", 'left')
                ->groupStart()->like($userTable . '.username', $search)->orLike($identities . '.secret', $search)->groupEnd();
        }

        return $users->orderBy($userTable . '.' . $this->sort(), $this->direction())->orderBy($userTable . '.id', 'DESC');
    }

    private function editableUser(int $userId): ?User
    {
        if (! auth()->user()?->can('users.manage-admins') || auth()->id() === $userId) {
            return null;
        }

        $user = auth()->getProvider()->findById($userId);
        if (! $user || $user->inGroup('superadmin') || array_diff($user->getGroups() ?? [], ['user', 'admin']) !== []) {
            return null;
        }

        return $user;
    }

    private function sort(): string
    {
        $sort = (string) $this->request->getGet('sort');

        return in_array($sort, ['username', 'created_at'], true) ? $sort : 'created_at';
    }

    private function direction(): string
    {
        return strtoupper((string) $this->request->getGet('direction')) === 'ASC' ? 'ASC' : 'DESC';
    }

    private function csvValue(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    private function csvResponse(string $filename, string $contents): ResponseInterface
    {
        return $this->response->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setContentType('text/csv', 'UTF-8')->setBody($contents);
    }
}
