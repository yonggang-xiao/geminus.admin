<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Entities\AdminUser;

/**
 * @internal
 */
final class RoleSettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testOnlySuperadminCanManageRolesAndPermissions(): void
    {
        $this->get('/en/admin/settings/roles')->assertRedirect();
        $this->post('/en/admin/settings/roles', [csrf_token() => csrf_hash(), 'name' => 'operator', 'title' => 'Operator'])->assertRedirect();

        $this->loginAs('developer');
        $this->get('/en/admin/settings/roles')->assertRedirect();
        $this->post('/en/admin/settings/roles/admin/permissions', [csrf_token() => csrf_hash(), 'permissions' => ['admin.settings']])->assertRedirect();
        $this->post('/en/admin/settings/roles/developer', [csrf_token() => csrf_hash(), 'title' => 'Changed'])->assertRedirect();
        $this->post('/en/admin/settings/permissions', [csrf_token() => csrf_hash(), 'name' => 'reports.view', 'description' => 'View reports'])->assertRedirect();
        $this->post('/en/admin/settings/permissions/users.create', [csrf_token() => csrf_hash(), 'description' => 'Changed'])->assertRedirect();
        $this->assertArrayNotHasKey('reports.view', setting('AuthGroups.permissions'));
        $this->assertNotSame('Changed', setting('AuthGroups.permissions')['users.create']);
        $this->assertNotSame('Changed', setting('AuthGroups.groups')['developer']['title']);
    }

    public function testPermissionChangeRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/roles/admin/permissions', ['permissions' => ['admin.settings']]);
    }

    public function testPermissionDescriptionCanBeUpdatedWithoutChangingGrants(): void
    {
        $this->loginAs('superadmin');
        $editPage = $this->get('/en/admin/settings/roles?view=permissions&role=developer&permission=users.create');
        $editPage->assertOK();
        $this->assertStringContainsString('action="/en/admin/settings/permissions/users.create?role=developer"', $editPage->response()->getBody());
        $this->assertStringContainsString('value="users.create" readonly', $editPage->response()->getBody());
        $this->assertStringContainsString('value="' . esc(setting('AuthGroups.permissions')['users.create']) . '"', $editPage->response()->getBody());
        $this->assertStringContainsString('cannot be renamed', $editPage->response()->getBody());
        $invalidPage = $this->get('/en/admin/settings/roles?view=permissions&permission=unknown.permission');
        $this->assertStringContainsString('action="/en/admin/settings/permissions?role=admin"', $invalidPage->response()->getBody());

        $before           = setting('AuthGroups.matrix');
        $otherDescription = setting('AuthGroups.permissions')['admin.settings'];

        $result = $this->post('/en/admin/settings/permissions/users.create?role=developer', [csrf_token() => csrf_hash(), 'description' => 'Add users', 'name' => 'admin.settings']);
        $result->assertRedirect();
        $this->assertStringEndsWith('/en/admin/settings/roles?view=permissions&permission=users.create&role=developer', $result->response()->getHeaderLine('Location'));
        $this->assertSame('Add users', setting('AuthGroups.permissions')['users.create']);
        $this->assertSame($otherDescription, setting('AuthGroups.permissions')['admin.settings']);
        $this->assertSame($before, setting('AuthGroups.matrix'));

        $this->post('/en/admin/settings/permissions/users.create', [csrf_token() => csrf_hash(), 'description' => ''])->assertRedirect();
        $this->assertNotEmpty(session('permission_edit_errors.description'));
        $this->assertSame('Add users', setting('AuthGroups.permissions')['users.create']);
        $this->post('/en/admin/settings/permissions/missing.view', [csrf_token() => csrf_hash(), 'description' => 'No'])->assertStatus(404);
    }

    public function testPermissionEditRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/permissions/users.create', ['description' => 'Changed']);
    }

    public function testRoleMetadataCanBeUpdatedWithoutChangingGrants(): void
    {
        $this->loginAs('superadmin');
        $originalGroups = setting('AuthGroups.groups');

        try {
            $rolePage = $this->get('/en/admin/settings/roles?role=developer');
            $this->assertStringContainsString('href="/en/admin/settings/roles?view=edit-role&role=developer"', $rolePage->response()->getBody());
            $editPage = $this->get('/en/admin/settings/roles?view=edit-role&role=developer');
            $editPage->assertOK();
            $this->assertStringContainsString('action="/en/admin/settings/roles/developer"', $editPage->response()->getBody());
            $this->assertStringContainsString('value="developer" readonly', $editPage->response()->getBody());
            $this->assertStringContainsString('value="' . esc(setting('AuthGroups.groups')['developer']['title']) . '"', $editPage->response()->getBody());
            $this->assertStringContainsString('value="' . esc(setting('AuthGroups.groups')['developer']['description']) . '"', $editPage->response()->getBody());
            $this->assertStringNotContainsString('name="name"', $editPage->response()->getBody());

            $before = setting('AuthGroups.matrix');

            $saved = $this->post('/en/admin/settings/roles/developer', [csrf_token() => csrf_hash(), 'title' => 'Engineers', 'description' => 'Engineering team', 'name' => 'superadmin']);
            $saved->assertRedirect();
            $this->assertStringEndsWith('/en/admin/settings/roles?role=developer', $saved->response()->getHeaderLine('Location'));
            $this->assertSame('Engineers', setting('AuthGroups.groups')['developer']['title']);
            $this->assertSame('Engineering team', setting('AuthGroups.groups')['developer']['description']);
            $this->assertSame($before, setting('AuthGroups.matrix'));
            $this->assertArrayNotHasKey('Engineers', setting('AuthGroups.groups'));

            $this->post('/en/admin/settings/roles/developer', [csrf_token() => csrf_hash(), 'title' => ''])->assertRedirect();
            $this->assertNotEmpty(session('role_edit_errors.title'));
            $this->assertSame('Engineers', setting('AuthGroups.groups')['developer']['title']);
            $this->post('/en/admin/settings/roles/unknown', [csrf_token() => csrf_hash(), 'title' => 'No'])->assertStatus(404);
        } finally {
            setting('AuthGroups.groups', $originalGroups);
        }
    }

    public function testSuperadminDisplaysEffectivePermissionsAndGrantSources(): void
    {
        $this->loginAs('superadmin');
        $originalMatrix       = setting('AuthGroups.matrix');
        $matrix               = setting('AuthGroups.matrix');
        $matrix['superadmin'] = ['admin.*', 'users.create', 'legacy.*'];
        setting('AuthGroups.matrix', $matrix);

        try {
            $page = $this->get('/en/admin/settings/roles?role=superadmin');
            $page->assertOK();
            $body = $page->response()->getBody();
            $this->assertStringContainsString('admin.settings', $body);
            $this->assertStringContainsString('users.create', $body);
            $this->assertStringNotContainsString('users.edit', $body);
            $this->assertStringContainsString('Granted by', $body);
            $this->assertStringContainsString('3 of ' . count(setting('AuthGroups.permissions')) . ' catalog permissions granted', $body);
            $this->assertStringContainsString('legacy.*', $body);
            $this->assertStringContainsString('<details>', $body);
            $this->assertStringNotContainsString('action="/en/admin/settings/roles/superadmin/permissions"', $body);
            $this->assertSame(['admin.*', 'users.create', 'legacy.*'], setting('AuthGroups.matrix')['superadmin']);

            $this->get('/zh-Hans/admin/settings/roles?role=superadmin')->assertSee('授权来源');
            $this->get('/zh-Hant/admin/settings/roles?role=superadmin')->assertSee('授權來源');

            $matrix['superadmin'] = [];
            setting('AuthGroups.matrix', $matrix);
            $emptyPage = $this->get('/en/admin/settings/roles?role=superadmin');
            $emptyPage->assertSee('No catalog permissions are currently granted.');
            $this->assertStringNotContainsString('<details>', $emptyPage->response()->getBody());
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    public function testRoleEditRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/roles/developer', ['title' => 'Changed']);
    }

    public function testNewRoleAndPermissionCanBeAssigned(): void
    {
        $this->loginAs('superadmin');
        $page = $this->get('/en/admin/settings/roles');
        $page->assertOK();
        $page->assertSee('Roles and permissions');
        $this->assertStringContainsString('name="' . csrf_token() . '"', $page->response()->getBody());
        $this->assertStringContainsString('action="/en/admin/settings/roles/admin/permissions"', $page->response()->getBody());
        $selectedPage = $this->get('/en/admin/settings/roles?role=developer');
        $this->assertStringContainsString('action="/en/admin/settings/roles/developer/permissions"', $selectedPage->response()->getBody());
        $this->assertStringNotContainsString('action="/en/admin/settings/roles/admin/permissions"', $selectedPage->response()->getBody());
        $newRolePage = $this->get('/en/admin/settings/roles?view=new-role');
        $this->assertStringContainsString('action="/en/admin/settings/roles"', $newRolePage->response()->getBody());
        $this->assertStringNotContainsString('action="/en/admin/settings/roles/admin/permissions"', $newRolePage->response()->getBody());
        $catalogPage = $this->get('/en/admin/settings/roles?view=permissions');
        $catalogPage->assertSee('Permission catalog');
        $catalogPage->assertSee('Use domain.ability (e.g. reports.view).');
        $this->assertStringContainsString('aria-describedby="permission-name-hint"', $catalogPage->response()->getBody());
        $this->assertStringContainsString('action="/en/admin/settings/permissions?role=admin"', $catalogPage->response()->getBody());
        $this->assertStringNotContainsString('action="/en/admin/settings/roles/admin/permissions"', $catalogPage->response()->getBody());
        $this->get('/zh-Hans/admin/settings/roles?view=permissions')->assertSee('格式为 domain.ability');
        $this->get('/zh-Hant/admin/settings/roles?view=permissions')->assertSee('格式為 domain.ability');
        $developerCatalog = $this->get('/en/admin/settings/roles?view=permissions&role=developer');
        $this->assertStringContainsString('href="/en/admin/settings/roles?role=developer"', $developerCatalog->response()->getBody());

        $createdRole = $this->post('/en/admin/settings/roles', [csrf_token() => csrf_hash(), 'name' => 'operator', 'title' => 'Operator', 'description' => 'Operations']);
        $createdRole->assertRedirect();
        $this->assertStringEndsWith('/en/admin/settings/roles?role=operator', $createdRole->response()->getHeaderLine('Location'));
        $createdPermission = $this->post('/en/admin/settings/permissions', [csrf_token() => csrf_hash(), 'name' => 'reports.view', 'description' => 'View reports']);
        $createdPermission->assertRedirect();
        $this->assertStringEndsWith('/en/admin/settings/roles?view=permissions', $createdPermission->response()->getHeaderLine('Location'));
        $contextRedirect = $this->post('/en/admin/settings/permissions?role=developer', [csrf_token() => csrf_hash(), 'name' => 'reports.edit', 'description' => 'Edit reports']);
        $this->assertStringEndsWith('/en/admin/settings/roles?view=permissions&role=developer', $contextRedirect->response()->getHeaderLine('Location'));
        $this->assertSame('Operator', setting('AuthGroups.groups')['operator']['title']);
        $this->assertSame('View reports', setting('AuthGroups.permissions')['reports.view']);
        $this->assertTrue(auth()->user()->can('reports.view'));

        $saved = $this->post('/en/admin/settings/roles/operator/permissions', [csrf_token() => csrf_hash(), 'permissions' => ['reports.view']]);
        $saved->assertRedirect();
        $this->assertStringEndsWith('/en/admin/settings/roles?role=operator', $saved->response()->getHeaderLine('Location'));
        $this->assertSame(['reports.view'], setting('AuthGroups.matrix')['operator']);

        $user        = new AdminUser(['username' => 'operatoruser']);
        $user->email = 'operator@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('operator');
        $this->assertTrue($user->can('reports.view'));
        $this->assertFalse($user->can('admin.settings'));
        $this->post('/en/admin/settings/roles/operator/permissions', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame([], setting('AuthGroups.matrix')['operator']);
        $this->assertFalse($user->can('reports.view'));
        $this->get('/zh-Hans/admin/settings/roles')->assertSee('角色与权限');
        $this->get('/zh-Hant/admin/settings/roles')->assertSee('角色與權限');
    }

    public function testInvalidChangesNeverModifySettingsOrSuperadmin(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/settings/roles', [csrf_token() => csrf_hash(), 'name' => 'superadmin', 'title' => 'Other'])->assertRedirect();
        $this->assertNotEmpty(session('role_errors.name'));
        $this->post('/en/admin/settings/permissions', [csrf_token() => csrf_hash(), 'name' => 'admin.*', 'description' => 'All'])->assertRedirect();
        $this->assertNotEmpty(session('permission_errors.name'));

        $before = setting('AuthGroups.matrix');
        $this->post('/en/admin/settings/roles/admin/permissions', [csrf_token() => csrf_hash(), 'permissions' => ['admin.settings', 'unknown.permission']])->assertRedirect();
        $this->assertSame($before, setting('AuthGroups.matrix'));
        $this->post('/en/admin/settings/roles/admin/permissions', [csrf_token() => csrf_hash(), 'permissions' => [['admin.settings']]])->assertRedirect();
        $this->assertSame($before, setting('AuthGroups.matrix'));
        $this->post('/en/admin/settings/roles/superadmin/permissions', [csrf_token() => csrf_hash(), 'permissions' => []])->assertStatus(404);
        $this->assertSame($before, setting('AuthGroups.matrix'));
    }

    public function testUnchangedWildcardGrantsArePreserved(): void
    {
        $this->loginAs('superadmin');
        $matrix                = setting('AuthGroups.matrix');
        $matrix['developer'][] = 'admin.*';
        setting('AuthGroups.matrix', $matrix);

        $permissions = array_keys(setting('AuthGroups.permissions'));
        $selected    = array_values(array_filter($permissions, static fn (string $permission): bool => str_starts_with($permission, 'admin.') || in_array($permission, $matrix['developer'], true)));
        $page        = $this->get('/en/admin/settings/roles?role=developer');
        $this->assertStringContainsString('name="grants[]" value="admin.&#x2A;" checked', $page->response()->getBody());
        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected, 'grants' => ['admin.*']])->assertRedirect();
        $this->assertContains('admin.*', setting('AuthGroups.matrix')['developer']);

        $selected = array_values(array_diff($selected, ['admin.settings']));
        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected, 'grants' => ['admin.*']])->assertRedirect();
        $this->assertSame(lang('Admin.wildcardPermissionConflict'), session('alert')['message']);
        $this->assertContains('admin.*', setting('AuthGroups.matrix')['developer']);

        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected])->assertRedirect();
        $this->assertNotContains('admin.*', setting('AuthGroups.matrix')['developer']);

        $matrix                = setting('AuthGroups.matrix');
        $matrix['developer'][] = 'internal.export';
        setting('AuthGroups.matrix', $matrix);
        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected, 'grants' => ['forged.access']])->assertRedirect();
        $this->assertContains('internal.export', setting('AuthGroups.matrix')['developer']);
        $this->post('/en/admin/settings/roles/developer/permissions', [csrf_token() => csrf_hash(), 'permissions' => $selected])->assertRedirect();
        $this->assertNotContains('internal.export', setting('AuthGroups.matrix')['developer']);
    }

    public function testDelegatedManagerCannotAssignMorePowerfulRole(): void
    {
        $this->loginAs('superadmin');
        $groups            = setting('AuthGroups.groups');
        $groups['manager'] = ['title' => 'Manager', 'description' => ''];
        setting('AuthGroups.groups', $groups);
        $matrix            = setting('AuthGroups.matrix');
        $matrix['manager'] = ['admin.access', 'users.manage-admins'];
        setting('AuthGroups.matrix', $matrix);

        $target        = new AdminUser(['username' => 'manageduser']);
        $target->email = 'managed@example.com';
        $target->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($target);
        $target = $users->findById($users->getInsertID());
        $target->addGroup('user');
        auth()->logout();
        $this->loginAs('manager');

        $page = $this->get('/en/admin/users/' . $target->id . '/edit');
        $page->assertOK();
        $this->assertStringNotContainsString('value="developer"', $page->response()->getBody());
        $this->post('/en/admin/users/' . $target->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'manageduser',
            'email'      => 'managed@example.com',
            'role'       => 'developer',
            'status'     => 'enabled',
        ])->assertRedirect();
        $this->assertNotEmpty(session('user_errors.role'));
        $this->assertSame(['user'], $users->findById($target->id)->getGroups());

        $matrix              = setting('AuthGroups.matrix');
        $matrix['developer'] = ['internal.export'];
        setting('AuthGroups.matrix', $matrix);
        $this->assertArrayNotHasKey('internal.export', setting('AuthGroups.permissions'));
        $page = $this->get('/en/admin/users/' . $target->id . '/edit');
        $this->assertStringNotContainsString('value="developer"', $page->response()->getBody());
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'rolesettings' . $group]);
        $user->email = 'rolesettings' . $group . '@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }
}
