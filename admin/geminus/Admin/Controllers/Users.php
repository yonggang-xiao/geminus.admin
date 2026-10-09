<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Geminus\Admin\Libraries\DataManagement\Attachments;
use Geminus\Admin\Libraries\DataManagement\Csv;
use Geminus\Admin\Libraries\DataManagement\ListQuery;
use Geminus\Admin\Libraries\MailTemplates;
use Geminus\Admin\Libraries\UserCsvImport;
use Geminus\Admin\Libraries\UserProvisioning;
use Geminus\Admin\Models\AttachmentModel;
use InvalidArgumentException;
use Throwable;

class Users extends BaseController
{
    private const EXPORT_LIMIT = 10000;

    public function __construct(private readonly ?Attachments $attachments = null)
    {
    }

    public function index(): ResponseInterface|string
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');
        $query                = $this->listQuery();
        $users                = $this->filteredUsers($query);
        $pageUsers            = $users->withIdentities()->withGroups()->withPermissions()->paginate($query->perPage);
        $editStates           = [];
        $roleNames            = [];
        $groups               = setting('AuthGroups.groups');
        $permissions          = array_keys(setting('AuthGroups.permissions'));
        $effectivePermissions = [];

        foreach ($pageUsers as $user) {
            $editStates[$user->id]           = $this->editState($user);
            $userGroups                      = $user->getGroups() ?? [];
            $roleNames[$user->id]            = implode(', ', array_map(static fn (string $group): string => $groups[$group]['title'] ?? $group, $userGroups));
            $effectivePermissions[$user->id] = array_values(array_filter($permissions, static fn (string $permission): bool => $user->can($permission)));
        }

        $invitationStatuses = [];
        if ($pageUsers !== []) {
            $latest = db_connect()->table('email_delivery_logs')
                ->select('MAX(id) AS id')
                ->whereIn('invited_user_id', array_map(static fn (User $user): int => $user->id, $pageUsers))
                ->groupBy('invited_user_id')->get()->getResultArray();
            if ($latest !== []) {
                $logs = db_connect()->table('email_delivery_logs')
                    ->select('invited_user_id, status')
                    ->whereIn('id', array_column($latest, 'id'))->get()->getResultArray();

                foreach ($logs as $log) {
                    $invitationStatuses[$log['invited_user_id']] = $log['status'];
                }
            }
        }

        return view('Geminus\Admin\Views\users', [
            'me'                   => auth()->user(),
            'page_title'           => lang('Admin.users'),
            'users'                => $pageUsers,
            'editStates'           => $editStates,
            'roleNames'            => $roleNames,
            'effectivePermissions' => $effectivePermissions,
            'invitationStatuses'   => $invitationStatuses,
            'pager'                => $users->pager,
            'search'               => $query->search,
            'sort'                 => $query->sort,
            'direction'            => $query->direction,
            'createdRange'         => $query->range('created', 'date'),
            'report'               => session('user_import_report'),
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
            'role'       => $user->getGroups()[0] ?? setting('AuthGroups.defaultGroup'),
            'roles'      => $this->assignableRoles(),
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

    public function attachments(int $userId): ResponseInterface|string
    {
        $user = $this->editableUser($userId);
        if ($user === null) {
            return $this->response->setStatusCode(404);
        }
        $model = new AttachmentModel();
        $this->response->setHeader('Cache-Control', 'private, no-store');

        return view('Geminus\Admin\Views\user_attachments', [
            'me'          => auth()->user(), 'user' => $user, 'page_title' => lang('Admin.attachments'),
            'attachments' => $model->forResource('user', $userId)->orderBy('id', 'DESC')->paginate(20),
            'pager'       => $model->pager,
            'accept'      => implode(',', array_map(static fn (string $extension): string => '.' . $extension, array_keys(Attachments::FILE_TYPES))),
        ]);
    }

    public function uploadAttachment(int $userId): RedirectResponse|ResponseInterface
    {
        if ($this->editableUser($userId) === null) {
            return $this->response->setStatusCode(404);
        }

        try {
            ($this->attachments ?? new Attachments())->upload('user', $userId, $this->request->getFile('file'), (int) auth()->id());
        } catch (InvalidArgumentException $exception) {
            return redirect()->to(route_to('admin/users/attachments', $userId))->with('attachment_errors', ['file' => lang('Admin.attachmentInvalid')]);
        } catch (Throwable $exception) {
            log_message('error', 'Attachment upload failed: {type}', ['type' => $exception::class]);

            return redirect()->to(route_to('admin/users/attachments', $userId))->with('alert', ['type' => 'danger', 'message' => lang('Admin.attachmentFailed')]);
        }

        return redirect()->to(route_to('admin/users/attachments', $userId))->with('alert', ['type' => 'success', 'message' => lang('Admin.attachmentSaved')]);
    }

    public function downloadAttachment(int $userId, int $attachmentId): ResponseInterface
    {
        if ($this->editableUser($userId) === null) {
            return $this->response->setStatusCode(404);
        }
        $service    = $this->attachments ?? new Attachments();
        $attachment = $service->find('user', $userId, $attachmentId);
        $path       = $attachment === null ? null : $service->path($attachment);
        if ($path === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->download($path, null)->setFileName($attachment['original_name'])
            ->setHeader('Cache-Control', 'private, no-store')->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function removeAttachment(int $userId, int $attachmentId): RedirectResponse|ResponseInterface
    {
        if ($this->editableUser($userId) === null) {
            return $this->response->setStatusCode(404);
        }

        try {
            if (! ($this->attachments ?? new Attachments())->remove('user', $userId, $attachmentId)) {
                return $this->response->setStatusCode(404);
            }
        } catch (Throwable $exception) {
            log_message('error', 'Attachment removal failed: {type}', ['type' => $exception::class]);

            return redirect()->to(route_to('admin/users/attachments', $userId))->with('alert', ['type' => 'danger', 'message' => lang('Admin.attachmentFailed')]);
        }

        return redirect()->to(route_to('admin/users/attachments', $userId))->with('alert', ['type' => 'success', 'message' => lang('Admin.attachmentRemoved')]);
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

        $validation    = service('validation');
        $usernameRules = config('Auth')->usernameValidationRules;
        if ($this->request->getPost('username') === $user->username) {
            $usernameRules['rules'] = ['required'];
        }
        $validation->setRules([
            'username' => $usernameRules,
            'email'    => config('Auth')->emailValidationRules,
            'role'     => 'required|in_list[' . implode(',', array_keys($this->assignableRoles())) . ']',
            'status'   => 'required|in_list[enabled,banned]',
        ]);
        $input = [
            'username' => trim((string) $this->request->getPost('username')),
            'email'    => strtolower(trim((string) $this->request->getPost('email'))),
            'role'     => $this->request->getPost('role'),
            'status'   => $this->request->getPost('status'),
        ];
        if (! $validation->run($input)) {
            return redirect()->to(route_to('admin/users/edit', $userId))->withInput()->with('user_errors', $validation->getErrors());
        }

        $data   = $validation->getValidated();
        $result = (new UserProvisioning())->updateAccount($user, $data['username'], $data['email'], $data['role'], $data['status']);
        if ($result === 'username' || $result === 'duplicate') {
            $field = $result === 'username' ? 'username' : 'email';

            return redirect()->to(route_to('admin/users/edit', $userId))->withInput()->with('user_errors', [$field => lang('Admin.userReason_' . $result)]);
        }
        if ($result !== 'updated') {
            return redirect()->to(route_to('admin/users/edit', $userId))->withInput()->with('alert', ['type' => 'danger', 'message' => lang('Admin.userUpdateFailed')]);
        }

        return redirect()->to(route_to('admin/users'))->with('alert', ['type' => 'success', 'message' => lang('Admin.userSaved')]);
    }

    public function invite(int $userId): RedirectResponse|ResponseInterface
    {
        $user = $this->editableUser($userId);
        if ($user === null) {
            return $this->response->setStatusCode(404);
        }

        if (! setting('Auth.allowMagicLinkLogins') || ! service('settings')->get('Email.fromEmail') || ! $user->email || $user->isBanned()) {
            return redirect()->to(route_to('admin/users'))->with('alert', ['type' => 'danger', 'message' => lang('Admin.inviteUnavailable')]);
        }

        try {
            $templates = new MailTemplates();
            $template  = $templates->render('invitation', $this->request->getLocale(), [
                'username' => $user->username,
                'link'     => url_to('magic-link'),
            ]);
            $email = service('email');
            $email->clear();
            $email->setFrom(service('settings')->get('Email.fromEmail'), service('settings')->get('Email.fromName') ?? '');
            $email->setTo($user->email);
            $email->setSubject($template['subject']);
            $email->setMailType('html');
            $email->setMessage($templates->renderHtml($template));
            $email->setInvitationUserId($user->id);
            $sent = $email->send();
            if (! $sent) {
                log_message('error', 'User invitation queue push failed.');
            }
        } catch (Throwable $exception) {
            log_message('error', 'User invitation delivery failed: {type}', ['type' => $exception::class]);
            $sent = false;
        }

        return redirect()->to(route_to('admin/users'))->with('alert', [
            'type'    => $sent ? 'success' : 'danger',
            'message' => lang($sent ? 'Admin.userInviteQueued' : 'Admin.userInviteFailed'),
        ]);
    }

    public function template(): ResponseInterface
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        return $this->csvResponse('users-template.csv', (new Csv())->write(['username', 'email']));
    }

    public function export(): ResponseInterface
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return $this->response->setStatusCode(403);
        }

        $users = $this->filteredUsers($this->listQuery())->withIdentities()->findAll(self::EXPORT_LIMIT + 1);
        if (count($users) > self::EXPORT_LIMIT) {
            return $this->response->setStatusCode(413)->setBody(lang('Admin.exportLimit'));
        }

        $csv = (new Csv())->write(['username', 'email'], array_map(static fn (User $user): array => [(string) $user->username, (string) $user->email], $users), self::EXPORT_LIMIT);

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

    private function filteredUsers(ListQuery $query): UserModel
    {
        $users      = model(get_class(auth()->getProvider()), false);
        $userTable  = config('Auth')->tables['users'];
        $identities = config('Auth')->tables['identities'];

        if ($query->search !== '') {
            $users->select($userTable . '.*')->join($identities, $identities . '.user_id = ' . $userTable . '.id AND ' . $identities . ".type = '" . Session::ID_TYPE_EMAIL_PASSWORD . "'", 'left');
        }

        $query->apply($users, [$userTable . '.username', $identities . '.secret'], stableField: $userTable . '.id', ranges: ['created' => ['field' => $userTable . '.created_at', 'type' => 'date']]);

        return $users;
    }

    private function listQuery(): ListQuery
    {
        $db         = db_connect(config('Auth')->DBGroup);
        $userTable  = config('Auth')->tables['users'];
        $identities = $db->prefixTable(config('Auth')->tables['identities']);

        return new ListQuery($this->request->getGet(), [
            'username'   => $userTable . '.username',
            'created_at' => $userTable . '.created_at',
            'email'      => ['field' => '(SELECT MIN(LOWER(' . $identities . '.secret)) FROM ' . $identities . ' WHERE ' . $identities . '.user_id = ' . $db->prefixTable($userTable) . '.id AND ' . $identities . ".type = '" . Session::ID_TYPE_EMAIL_PASSWORD . "')", 'escape' => false],
        ], 'created_at');
    }

    private function editableUser(int $userId): ?User
    {
        if (! auth()->user()?->can('users.manage-admins')) {
            return null;
        }

        $user = auth()->getProvider()->findById($userId);
        if (! $user || $this->editState($user) !== 'editable') {
            return null;
        }

        return $user;
    }

    private function editState(User $user): string
    {
        if ($user->id === auth()->id()) {
            return 'self';
        }

        $groups = $user->getGroups() ?? [];
        if (count($groups) > 1 || array_diff($groups, array_keys($this->assignableRoles())) !== []) {
            return 'protected';
        }

        return 'editable';
    }

    private function assignableRoles(): array
    {
        $roles = setting('AuthGroups.groups');
        unset($roles['superadmin']);

        if (! auth()->user()?->inGroup('superadmin')) {
            $matrix = setting('AuthGroups.matrix');

            foreach ($roles as $name => $details) {
                foreach ($matrix[$name] ?? [] as $grant) {
                    if (str_contains($grant, '*') || ! auth()->user()->can($grant)) {
                        unset($roles[$name]);

                        break;
                    }
                }
            }
        }

        return $roles;
    }

    private function csvResponse(string $filename, string $contents): ResponseInterface
    {
        return $this->response->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setContentType('text/csv', 'UTF-8')->setBody($contents);
    }
}
