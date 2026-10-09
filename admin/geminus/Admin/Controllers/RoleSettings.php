<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authorization\PermissionMatcher;

class RoleSettings extends BaseController
{
    public function index(): ResponseInterface|string
    {
        if (! auth()->user()?->inGroup('superadmin')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');

        $groups = setting('AuthGroups.groups');
        $role   = $this->request->getGet('role');
        if (! is_string($role) || ! isset($groups[$role])) {
            $role = isset($groups['admin']) ? 'admin' : array_key_first($groups);
        }
        $view = $this->request->getGet('view');
        if (! in_array($view, ['permissions', 'new-role', 'edit-role'], true)) {
            $view = 'roles';
        }
        $permissions       = setting('AuthGroups.permissions');
        $editingPermission = $this->request->getGet('permission');
        if ($view !== 'permissions' || ! is_string($editingPermission) || ! isset($permissions[$editingPermission])) {
            $editingPermission = null;
        }
        $permissionGroups          = [];
        $effectivePermissionGroups = [];
        $matrix                    = setting('AuthGroups.matrix');
        $roleGrants                = $matrix[$role] ?? [];
        $protectedRole             = $role === 'superadmin';
        $checked                   = [];
        $extraGrants               = [];

        foreach ($permissions as $permission => $description) {
            $domain                                 = explode('.', $permission, 2)[0];
            $permissionGroups[$domain][$permission] = $description;
            if (PermissionMatcher::matches($permission, $roleGrants)) {
                $checked[$permission] = true;
                if ($protectedRole) {
                    $effectivePermissionGroups[$domain][$permission] = [
                        'description' => $description,
                        'grants'      => array_values(array_filter($roleGrants, static fn (string $grant): bool => PermissionMatcher::matches($permission, [$grant]))),
                    ];
                }
            }
        }

        foreach ($roleGrants as $grant) {
            if (! isset($permissions[$grant])) {
                $extraGrants[] = $grant;
            }
        }

        return view('Geminus\Admin\Views\settings_roles', [
            'me'                        => auth()->user(),
            'page_title'                => lang('Admin.roleSettings'),
            'groups'                    => $groups,
            'selectedRole'              => $role,
            'view'                      => $view,
            'permissions'               => $permissions,
            'editingPermission'         => $editingPermission,
            'permissionGroups'          => $permissionGroups,
            'permissionDomainLabels'    => lang('Admin.permissionDomainLabels'),
            'effectivePermissionGroups' => $effectivePermissionGroups,
            'checked'                   => $checked,
            'extraGrants'               => $extraGrants,
            'roleGrants'                => $roleGrants,
            'protectedRole'             => $protectedRole,
        ]);
    }

    public function permissions(string $role): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->inGroup('superadmin')) {
            return $this->response->setStatusCode(403);
        }

        $groups      = setting('AuthGroups.groups');
        $permissions = setting('AuthGroups.permissions');

        if ($role === 'superadmin' || ! isset($groups[$role])) {
            return $this->response->setStatusCode(404);
        }

        $selected = $this->request->getPost('permissions') ?? [];
        if (! is_array($selected) || count($selected) > count($permissions) || array_filter($selected, static fn ($value): bool => ! is_string($value) || ! isset($permissions[$value])) !== []) {
            return redirect()->back()->with('alert', ['type' => 'danger', 'message' => lang('Admin.invalidRolePermissions')]);
        }

        $matrix   = setting('AuthGroups.matrix');
        $existing = $matrix[$role] ?? [];
        $grants   = $this->request->getPost('grants') ?? [];
        if (! is_array($grants) || array_filter($grants, static fn ($grant): bool => ! is_string($grant) || isset($permissions[$grant]) || ! in_array($grant, $existing, true)) !== []) {
            return redirect()->back()->with('alert', ['type' => 'danger', 'message' => lang('Admin.invalidRolePermissions')]);
        }

        $updated = array_values(array_unique([...$selected, ...$grants]));

        foreach (array_keys($permissions) as $permission) {
            if (! in_array($permission, $selected, true) && PermissionMatcher::matches($permission, $updated)) {
                return redirect()->back()->with('alert', ['type' => 'danger', 'message' => lang('Admin.wildcardPermissionConflict')]);
            }
        }

        $matrix[$role] = $updated;
        setting('AuthGroups.matrix', $matrix);

        return redirect()->to(route_to('admin/settings/roles') . '?role=' . rawurlencode($role))->with('alert', ['type' => 'success', 'message' => lang('Admin.rolePermissionsSaved')]);
    }

    public function createRole(): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->inGroup('superadmin')) {
            return $this->response->setStatusCode(403);
        }

        $validation = service('validation');
        $validation->setRules([
            'name'        => 'required|max_length[40]',
            'title'       => 'required|max_length[100]',
            'description' => 'permit_empty|max_length[255]',
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->back()->withInput()->with('role_errors', $validation->getErrors());
        }

        $data   = $validation->getValidated();
        $groups = setting('AuthGroups.groups');
        if (! preg_match('/\A[a-z][a-z0-9-]*\z/D', $data['name']) || isset($groups[$data['name']])) {
            return redirect()->back()->withInput()->with('role_errors', ['name' => lang('Admin.invalidRoleName')]);
        }

        $groups[$data['name']] = ['title' => $data['title'], 'description' => $data['description'] ?? ''];
        setting('AuthGroups.groups', $groups);

        return redirect()->to(route_to('admin/settings/roles') . '?role=' . rawurlencode($data['name']))->with('alert', ['type' => 'success', 'message' => lang('Admin.roleCreated')]);
    }

    public function updateRole(string $role): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->inGroup('superadmin')) {
            return $this->response->setStatusCode(403);
        }

        $groups = setting('AuthGroups.groups');
        if (! isset($groups[$role])) {
            return $this->response->setStatusCode(404);
        }

        $validation = service('validation');
        $validation->setRules([
            'title'       => 'required|max_length[100]',
            'description' => 'permit_empty|max_length[255]',
        ]);
        if (! $validation->run($this->request->getPost())) {
            return redirect()->back()->withInput()->with('role_edit_errors', $validation->getErrors());
        }

        $data                         = $validation->getValidated();
        $groups[$role]['title']       = $data['title'];
        $groups[$role]['description'] = $data['description'] ?? '';
        setting('AuthGroups.groups', $groups);

        return redirect()->to(route_to('admin/settings/roles') . '?role=' . rawurlencode($role))
            ->with('alert', ['type' => 'success', 'message' => lang('Admin.roleUpdated')]);
    }

    public function createPermission(): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->inGroup('superadmin')) {
            return $this->response->setStatusCode(403);
        }

        $validation = service('validation');
        $validation->setRules([
            'name'        => 'required|max_length[80]',
            'description' => 'required|max_length[255]',
        ]);

        if (! $validation->run($this->request->getPost())) {
            return redirect()->back()->withInput()->with('permission_errors', $validation->getErrors());
        }

        $data        = $validation->getValidated();
        $permissions = setting('AuthGroups.permissions');
        if (! preg_match('/\A[a-z][a-z0-9-]*\.[a-z][a-z0-9-]*\z/D', $data['name']) || isset($permissions[$data['name']])) {
            return redirect()->back()->withInput()->with('permission_errors', ['name' => lang('Admin.invalidPermissionName')]);
        }

        $permissions[$data['name']] = $data['description'];
        $matrix                     = setting('AuthGroups.matrix');
        if (! PermissionMatcher::matches($data['name'], $matrix['superadmin'] ?? [])) {
            $matrix['superadmin'][] = $data['name'];
        }
        service('settings')->setMany(['AuthGroups.permissions' => $permissions, 'AuthGroups.matrix' => $matrix]);

        $returnRole = $this->request->getGet('role');
        $query      = '?view=permissions';
        if (is_string($returnRole) && isset(setting('AuthGroups.groups')[$returnRole])) {
            $query .= '&role=' . rawurlencode($returnRole);
        }

        return redirect()->to(route_to('admin/settings/roles') . $query)->with('alert', ['type' => 'success', 'message' => lang('Admin.permissionCreated')]);
    }

    public function updatePermission(string $permission): RedirectResponse|ResponseInterface
    {
        if (! auth()->user()?->inGroup('superadmin')) {
            return $this->response->setStatusCode(403);
        }

        $permissions = setting('AuthGroups.permissions');
        if (! isset($permissions[$permission])) {
            return $this->response->setStatusCode(404);
        }

        $validation = service('validation');
        $validation->setRules(['description' => 'required|max_length[255]']);
        if (! $validation->run($this->request->getPost())) {
            return redirect()->back()->withInput()->with('permission_edit_errors', $validation->getErrors());
        }

        $permissions[$permission] = $validation->getValidated()['description'];
        setting('AuthGroups.permissions', $permissions);

        $query      = '?view=permissions&permission=' . rawurlencode($permission);
        $returnRole = $this->request->getGet('role');
        if (is_string($returnRole) && isset(setting('AuthGroups.groups')[$returnRole])) {
            $query .= '&role=' . rawurlencode($returnRole);
        }

        return redirect()->to(route_to('admin/settings/roles') . $query)
            ->with('alert', ['type' => 'success', 'message' => lang('Admin.permissionUpdated')]);
    }
}
