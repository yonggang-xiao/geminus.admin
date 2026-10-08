<?php

use CodeIgniter\Config\Services;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\MailTemplates;
use Geminus\Admin\Libraries\MicrosoftLinks;
use Geminus\Admin\Libraries\QueuedEmail;

/**
 * @internal
 */
final class UsersTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        service('settings')->forget(MailTemplates::settingKey('invitation', 'en', 'subject'));
        service('settings')->forget(MailTemplates::settingKey('invitation', 'en', 'body'));
        Services::resetSingle('email');
        Services::resetSingle('queue');
        auth()->logout();
        parent::tearDown();
    }

    public function testUserListAndExportRequireManagementPermission(): void
    {
        $this->get('/en/admin/users')->assertRedirect();
        $this->get('/en/admin/users/export')->assertRedirect();
        $this->loginAs('admin');
        $this->get('/en/admin/users')->assertRedirect();
        $this->get('/en/admin/users/export')->assertRedirect();
    }

    public function testUserListAndExportShareSearchFilter(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('searchable', 'searchable@example.com');
        $this->createUser('elsewhere', 'elsewhere@example.com');

        $page = $this->get('/en/admin/users?q=searchable%40example.com');
        $page->assertOK();
        $page->assertSee('searchable@example.com');
        $this->assertStringNotContainsString('elsewhere@example.com', $page->response()->getBody());

        $export = $this->get('/en/admin/users/export?q=searchable%40example.com');
        $export->assertOK();
        $this->assertStringContainsString('searchable@example.com', $export->response()->getBody());
        $this->assertStringNotContainsString('elsewhere@example.com', $export->response()->getBody());

        $this->createUser('Mixed.Name', 'Mixed.Address@example.com');

        foreach (['mixed.name', 'MIXED.ADDRESS@EXAMPLE.COM'] as $query) {
            $page = $this->get('/en/admin/users?q=' . rawurlencode($query));
            $page->assertSee('Mixed.Address@example.com');
            $export = $this->get('/en/admin/users/export?q=' . rawurlencode($query));
            $this->assertStringContainsString('Mixed.Address@example.com', $export->response()->getBody());
        }
    }

    public function testUserListDisplaysCreationTimeInViewerTimezone(): void
    {
        $this->loginAs('superadmin');
        $viewer           = auth()->user();
        $viewer->timezone = 'Asia/Shanghai';
        auth()->getProvider()->save($viewer);
        $this->createUser('dateduser', 'dateduser@example.com');
        $created = auth()->getProvider()->findByCredentials(['email' => 'dateduser@example.com']);

        $page = $this->get('/en/admin/users?q=dateduser');
        $page->assertOK();
        $this->assertStringContainsString('<td>' . $viewer->formatDateTime($created->created_at) . '</td>', $page->response()->getBody());
    }

    public function testUserListShowsEditActionAndProtectedAccountReason(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('editable', 'editable@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'editable@example.com']);
        $this->createUser('protected', 'protected@example.com');
        $protected = auth()->getProvider()->findByCredentials(['email' => 'protected@example.com']);
        $protected->addGroup('superadmin');

        $page = $this->get('/en/admin/users');
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertStringContainsString('href="' . route_to('admin/users/edit', $user->id) . '"', $body);
        $this->assertStringNotContainsString('href="' . route_to('admin/users/edit', auth()->id()) . '"', $body);
        $this->assertStringNotContainsString('href="' . route_to('admin/users/edit', $protected->id) . '"', $body);
        $this->assertStringContainsString('data-bs-target="#user-permissions-' . $protected->id . '"', $body);
        $this->assertStringContainsString('data-bs-target="#user-permissions-' . auth()->id() . '"', $body);
        $this->assertStringContainsString('Your account', $body);
        $this->assertStringContainsString('Protected account', $body);
        $this->assertStringContainsString('Enabled', $body);

        $empty = $this->get('/en/admin/users?q=no-such-user');
        $empty->assertOK();
        $this->assertStringContainsString('No users found.', $empty->response()->getBody());
        $this->assertStringContainsString('Clear', $empty->response()->getBody());
    }

    public function testUserListShowsConfiguredRoleTitles(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('developeruser', 'developer@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'developer@example.com']);
        $user->syncGroups('developer');

        $page = $this->get('/en/admin/users?q=developeruser');
        $page->assertOK();
        $body = $page->response()->getBody();
        $this->assertStringContainsString('<th scope="col">' . lang('Admin.userRole') . '</th>', $body);
        $this->assertStringContainsString('<td>Developer</td>', $body);

        $empty = $this->get('/en/admin/users?q=no-such-user');
        $this->assertStringContainsString('<tr><td colspan="7"', $empty->response()->getBody());
    }

    public function testUserListShowsEffectiveCatalogPermissions(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('grantviewer', 'grantviewer@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'grantviewer@example.com']);
        $user->syncGroups('beta');
        $user->addPermission('users.create');
        $originalMatrix   = setting('AuthGroups.matrix');
        $matrix           = $originalMatrix;
        $matrix['beta'][] = 'admin.*';
        $matrix['beta'][] = 'unlisted.*';
        setting('AuthGroups.matrix', $matrix);

        try {
            $page = $this->get('/en/admin/users?q=grantviewer');
            $page->assertOK();
            $body = $page->response()->getBody();
            $this->assertStringContainsString('beta.access', $body);
            $this->assertStringContainsString('users.create', $body);
            $this->assertStringContainsString('admin.settings', $body);
            $this->assertStringContainsString('Permissions: 4', $body);
            $this->assertStringNotContainsString('users.edit', $body);
            $this->assertStringNotContainsString('unlisted.*', $body);
            $this->assertStringContainsString('data-bs-toggle="offcanvas" data-bs-target="#user-permissions-' . $user->id . '"', $body);
            $this->assertStringContainsString('<th scope="col" class="text-end">Actions</th>', $body);
            $this->assertStringNotContainsString('<th scope="col">Effective permissions</th>', $body);
            $this->assertStringContainsString('id="user-permissions-' . $user->id . '" aria-labelledby="user-permissions-title-' . $user->id . '"', $body);
            $this->assertGreaterThan(strpos($body, '</table>'), strpos($body, 'class="offcanvas offcanvas-end"'));
            $this->assertStringNotContainsString('<details>', $body);

            $this->get('/zh-Hans/admin/users?q=grantviewer')->assertSee('有效权限');
            $this->get('/zh-Hant/admin/users?q=grantviewer')->assertSee('有效權限');
            $this->createUser('noaccessviewer', 'noaccessviewer@example.com');
            $emptyPermissions = $this->get('/en/admin/users?q=noaccessviewer');
            $emptyPermissions->assertSee('No catalog permissions');
            $emptyUser = auth()->getProvider()->findByCredentials(['email' => 'noaccessviewer@example.com']);
            $this->assertStringContainsString('data-bs-target="#user-permissions-' . $emptyUser->id . '"', $emptyPermissions->response()->getBody());
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    public function testUserListHeaderSortingKeepsSearchAndTogglesDirection(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('sortcase-z', 'sortcase-z@example.com');
        $this->createUser('sortcase-a', 'sortcase-a@example.com');

        $ascending = $this->get('/en/admin/users?q=sortcase&sort=username&direction=ASC');
        $ascending->assertOK();
        $body = $ascending->response()->getBody();
        $this->assertStringContainsString('class="table-sort asc"', $body);
        $this->assertStringContainsString('aria-sort="ascending"', $body);
        $this->assertStringContainsString('name="q" value="sortcase"', $body);
        $this->assertStringContainsString('name="direction" value="DESC"', $body);
        $this->assertStringContainsString('<td>sortcase-a</td>', $body);
        $this->assertStringContainsString('<td>sortcase-z</td>', $body);
        $this->assertLessThan(strpos($body, '<td>sortcase-z</td>'), strpos($body, '<td>sortcase-a</td>'));

        $descending = $this->get('/en/admin/users?q=sortcase&sort=username&direction=DESC');
        $descending->assertOK();
        $body = $descending->response()->getBody();
        $this->assertStringContainsString('class="table-sort desc"', $body);
        $this->assertStringContainsString('aria-sort="descending"', $body);
        $this->assertLessThan(strpos($body, '<td>sortcase-a</td>'), strpos($body, '<td>sortcase-z</td>'));
    }

    public function testEmailHeaderSortsAllUsersByEmail(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('alpha', 'zeta@example.com');
        $this->createUser('zulu', 'alpha@example.com');

        $ascending = $this->get('/en/admin/users?sort=email&direction=ASC');
        $ascending->assertOK();
        $body = $ascending->response()->getBody();
        $this->assertStringContainsString('class="table-sort asc"', $body);
        $this->assertStringContainsString('aria-sort="ascending"', $body);
        $this->assertStringContainsString('name="sort" value="email"', $body);
        $this->assertStringContainsString('name="direction" value="DESC"', $body);
        $this->assertStringContainsString('alpha@example.com', $body);
        $this->assertStringContainsString('zeta@example.com', $body);
        $this->assertLessThan(strpos($body, '<td>zeta@example.com</td>'), strpos($body, '<td>alpha@example.com</td>'));

        $descending = $this->get('/en/admin/users?sort=email&direction=DESC');
        $descending->assertOK();
        $body = $descending->response()->getBody();
        $this->assertStringContainsString('class="table-sort desc"', $body);
        $this->assertLessThan(strpos($body, '<td>alpha@example.com</td>'), strpos($body, '<td>zeta@example.com</td>'));

        $filtered = $this->get('/en/admin/users?q=example.com&sort=email&direction=ASC');
        $filtered->assertOK();
        $body = $filtered->response()->getBody();
        $this->assertStringContainsString('name="q" value="example.com"', $body);
        $this->assertLessThan(strpos($body, '<td>zeta@example.com</td>'), strpos($body, '<td>alpha@example.com</td>'));

        $export = $this->get('/en/admin/users/export?q=example.com&sort=email&direction=ASC');
        $export->assertOK();
        $csv = $export->response()->getBody();
        $this->assertLessThan(strpos($csv, 'zeta@example.com'), strpos($csv, 'alpha@example.com'));
    }

    public function testUsernameAndEmailSortingIgnoreCase(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('Zulu', 'Zoo@example.com');
        $this->createUser('apple', 'apple@example.com');

        foreach (['username', 'email'] as $sort) {
            $page = $this->get('/en/admin/users?q=example.com&sort=' . $sort . '&direction=ASC');
            $page->assertOK();
            $body = $page->response()->getBody();
            $this->assertLessThan(strpos($body, '<td>Zulu</td>'), strpos($body, '<td>apple</td>'));

            $export = $this->get('/en/admin/users/export?q=example.com&sort=' . $sort . '&direction=ASC');
            $this->assertLessThan(strpos($export->response()->getBody(), 'Zulu,Zoo@example.com'), strpos($export->response()->getBody(), 'apple,apple@example.com'));
        }
    }

    public function testOrdinaryUsernameLookupUsesCitext(): void
    {
        $this->createUser('Mixed.Name', 'mixed.name@example.com');
        $users = auth()->getProvider();

        $this->assertSame('Mixed.Name', $users->where('username', 'mixed.name')->first()->username);
        $this->assertSame('citext', $users->db->query("SELECT format_type(atttypid, atttypmod) AS type FROM pg_attribute WHERE attrelid = 'users'::regclass AND attname = 'username'")->getRow('type'));
    }

    public function testUsernameUniqueIndexRejectsDifferentCase(): void
    {
        $this->createUser('Mixed.Name', 'mixed.name@example.com');

        $this->expectException(DatabaseException::class);
        auth()->getProvider()->db->table(config('Auth')->tables['users'])->insert(['username' => 'mixed.name']);
    }

    public function testImportPostRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/users/import', []);
    }

    public function testImportRejectsMissingFileWithCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/users/import', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(lang('Admin.invalidUserCsv'), session('alert')['message']);
    }

    public function testForgedUploadIsRejected(): void
    {
        $this->loginAs('superadmin');
        $filename = tempnam(sys_get_temp_dir(), 'user-csv-');
        file_put_contents($filename, "username,email\nnewuser,newuser@example.com\nduplicate,NEWUSER@example.com\n");
        service('superglobals')->setFilesArray([
            'file' => ['name' => 'users.csv', 'type' => 'text/csv', 'tmp_name' => $filename, 'error' => UPLOAD_ERR_OK, 'size' => filesize($filename)],
        ]);

        try {
            $this->post('/en/admin/users/import', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->assertSame(lang('Admin.invalidUserCsv'), session('alert')['message']);
            $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'newuser@example.com']));
        } finally {
            service('superglobals')->setFilesArray([]);
            unlink($filename);
        }
    }

    public function testCsvExportEscapesSpreadsheetFormulas(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('=1+1', 'formula@example.com');

        $csv = $this->get('/en/admin/users/export?q=formula%40example.com');
        $csv->assertOK();
        $this->assertStringContainsString("'=1+1,formula@example.com", $csv->response()->getBody());
    }

    public function testSuperadminCanAssignAdminRoleAndBanImportedAccount(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('imported', 'imported@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'imported@example.com']);

        $this->get('/en/admin/users/' . $user->id . '/edit')->assertOK();
        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'imported', 'email' => 'imported@example.com', 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $updated = auth()->getProvider()->findById($user->id);
        $this->assertTrue($updated->inGroup('admin'));
        $this->assertTrue($updated->isBanned());
        $this->get('/en/admin/users/' . auth()->id() . '/edit')->assertStatus(404);
    }

    public function testEditRolesComeFromShieldConfiguration(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('developeruser', 'developer@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'developer@example.com']);
        $url  = '/en/admin/users/' . $user->id . '/edit';

        $form = $this->get($url);
        $form->assertOK();
        $body = $form->response()->getBody();
        $this->assertStringContainsString('<option value="developer">Developer</option>', $body);
        $this->assertStringNotContainsString('<option value="superadmin"', $body);

        $this->post($url, [csrf_token() => csrf_hash(), 'username' => 'developeruser', 'email' => 'developer@example.com', 'role' => 'developer', 'status' => 'enabled'])->assertRedirect();
        $this->assertTrue(auth()->getProvider()->findById($user->id)->inGroup('developer'));
        $this->assertStringContainsString('<option value="developer" selected>Developer</option>', $this->get($url)->response()->getBody());
    }

    public function testMultiGroupAccountCannotLoseGroupsThroughSingleRoleForm(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('multigroup', 'multigroup@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'multigroup@example.com']);
        $user->addGroup('admin', 'beta');
        $url = '/en/admin/users/' . $user->id . '/edit';

        $this->assertStringNotContainsString('href="' . route_to('admin/users/edit', $user->id) . '"', $this->get('/en/admin/users')->response()->getBody());
        $this->get($url)->assertStatus(404);
        $this->post($url, [csrf_token() => csrf_hash(), 'username' => 'multigroup', 'email' => 'multigroup@example.com', 'role' => 'user', 'status' => 'enabled'])->assertStatus(404);
        $this->assertTrue(auth()->getProvider()->findById($user->id)->inGroup('beta'));
    }

    public function testSuperadminCanUpdateUsernameAndEmailWithoutChangingPasswordOrMicrosoftIdentity(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('original', 'original@example.com');
        $user         = auth()->getProvider()->findByCredentials(['email' => 'original@example.com']);
        $passwordHash = $user->getEmailIdentity()->secret2;
        $form         = $this->get('/en/admin/users/' . $user->id . '/edit');
        $form->assertOK();
        $this->assertStringContainsString('name="username"', $form->response()->getBody());
        $this->assertStringContainsString('aria-describedby="user-username-hint"', $form->response()->getBody());
        $this->assertStringContainsString('id="user-username-hint" class="form-text">' . lang('Admin.usernameHint'), $form->response()->getBody());
        $this->assertStringContainsString('name="email"', $form->response()->getBody());
        model(UserIdentityModel::class)->create([
            'user_id' => $user->id,
            'type'    => MicrosoftLinks::IDENTITY_TYPE,
            'secret'  => 'tenant/object',
        ]);

        $this->post('/en/admin/users/' . $user->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'renamed',
            'email'      => 'NEW@example.com',
            'role'       => 'admin',
            'status'     => 'banned',
        ])->assertRedirect();

        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('renamed', $updated->username);
        $this->assertSame('new@example.com', $updated->email);
        $this->assertSame($passwordHash, $updated->getEmailIdentity()->secret2);
        $this->assertSame('tenant/object', $updated->getIdentity(MicrosoftLinks::IDENTITY_TYPE)->secret);
        $this->assertTrue($updated->inGroup('admin'));
        $this->assertTrue($updated->isBanned());
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'original@example.com']));
    }

    public function testDuplicateUsernameOrEmailDoesNotPartiallyUpdateAccount(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('existing', 'existing@example.com');
        $this->createUser('target', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $url  = '/en/admin/users/' . $user->id . '/edit';

        $this->post($url, [csrf_token() => csrf_hash(), 'username' => 'EXISTING', 'email' => 'changed@example.com', 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_username'), session('user_errors.username'));
        $this->post($url, [csrf_token() => csrf_hash(), 'username' => 'changed', 'email' => 'EXISTING@example.com', 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_duplicate'), session('user_errors.email'));

        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('target', $updated->username);
        $this->assertSame('target@example.com', $updated->email);
        $this->assertFalse($updated->inGroup('admin'));
        $this->assertFalse($updated->isBanned());
    }

    public function testEditingUsernameRejectsCaseInsensitiveConflictButAllowsUnchangedName(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('owner', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $this->createUser('TARGET', 'legacycase@example.com');

        $this->post('/en/admin/users/' . $user->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'target',
            'email'      => 'target@example.com',
            'role'       => 'admin',
            'status'     => 'banned',
        ])->assertRedirect();

        $this->assertSame(lang('Admin.userReason_username'), session('user_errors.username'));
        $updated = auth()->getProvider()->findById($user->id);
        $this->assertFalse($updated->inGroup('admin'));
        $this->assertFalse($updated->isBanned());

        $this->post('/en/admin/users/' . $user->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'owner',
            'email'      => 'target@example.com',
            'role'       => 'admin',
            'status'     => 'enabled',
        ])->assertRedirect();
        $this->assertTrue(auth()->getProvider()->findById($user->id)->inGroup('admin'));
    }

    public function testEmailOnlyUpdatePreservesPasswordHash(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('sameuser', 'before@example.com');
        $user         = auth()->getProvider()->findByCredentials(['email' => 'before@example.com']);
        $passwordHash = $user->getEmailIdentity()->secret2;

        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'sameuser', 'email' => 'after@example.com', 'role' => 'user', 'status' => 'enabled'])->assertRedirect();

        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('sameuser', $updated->username);
        $this->assertSame('after@example.com', $updated->email);
        $this->assertSame($passwordHash, $updated->getEmailIdentity()->secret2);
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'before@example.com']));
    }

    public function testEditingUserRequiresEmail(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('targetuser', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);

        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'renamed', 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $this->assertArrayHasKey('email', session('user_errors'));

        $updated = auth()->getProvider()->findById($user->id);
        $this->assertSame('targetuser', $updated->username);
        $this->assertSame('target@example.com', $updated->email);
        $this->assertFalse($updated->inGroup('admin'));
        $this->assertFalse($updated->isBanned());
    }

    public function testOrdinaryAdminCannotEditUsersAndInvalidRoleCannotBeAssigned(): void
    {
        $this->loginAs('admin');
        $this->createUser('targetuser', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'role' => 'admin', 'status' => 'enabled'])->assertRedirect();
        $this->assertFalse(auth()->getProvider()->findById($user->id)->inGroup('admin'));

        auth()->logout();
        $this->loginAs('superadmin');
        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'username' => 'targetuser', 'email' => 'target@example.com', 'role' => 'superadmin', 'status' => 'enabled'])->assertRedirect();
        $this->assertFalse(auth()->getProvider()->findById($user->id)->inGroup('superadmin'));
    }

    public function testRoleUpdateRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('targetuser', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/users/' . $user->id . '/edit', ['role' => 'admin', 'status' => 'enabled']);
    }

    public function testCreateUserSharesImportProvisioningRules(): void
    {
        $this->loginAs('superadmin');
        $form = $this->get('/en/admin/users/create');
        $form->assertOK();
        $this->assertStringContainsString('aria-describedby="new-username-hint"', $form->response()->getBody());
        $this->assertStringContainsString('id="new-username-hint" class="form-text">' . lang('Admin.usernameHint'), $form->response()->getBody());
        $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'createduser', 'email' => 'created@example.com'])->assertRedirect();
        $user = auth()->getProvider()->findByCredentials(['email' => 'created@example.com']);
        $this->assertNotNull($user);
        $this->assertTrue($user->inGroup('user'));
        $this->assertFalse($user->can('admin.access'));

        $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'otheruser', 'email' => 'CREATED@example.com'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_duplicate'), session('user_errors.email'));

        $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'CREATEDUSER', 'email' => 'another@example.com'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_username'), session('user_errors.username'));
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'another@example.com']));
    }

    public function testInviteReportsQueueAcceptanceAndOffersRetry(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        service('settings')->set('Email.fromEmail', 'sender@example.com');

        $page = $this->get('/en/admin/users?q=invitee');
        $page->assertOK();
        $this->assertStringContainsString('action="' . route_to('admin/users/invite', $user->id) . '"', $page->response()->getBody());
        $this->assertStringContainsString('Send invitation', $page->response()->getBody());

        foreach ([true, false] as $sent) {
            $email = $this->createMock(QueuedEmail::class);
            $email->expects($this->once())->method('setFrom')->with('sender@example.com');
            $email->expects($this->once())->method('setTo')->with('invitee@example.com');
            $email->expects($this->once())->method('setSubject')->with(lang('Admin.userInviteSubject'));
            $email->expects($this->once())->method('setMailType')->with('html');
            $email->expects($this->once())->method('setMessage')->with($this->callback(static fn (string $body): bool => str_contains($body, '<!DOCTYPE html PUBLIC')
                && str_contains($body, '<a href="' . url_to('magic-link') . '"')
                && str_contains($body, 'Hello invitee')
                && ! str_contains($body, 'GeminusAdmin')));
            $email->expects($this->once())->method('send')->willReturn($sent);
            $email->expects($this->never())->method('sendDirect');
            Services::injectMock('email', $email);

            $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
            $this->assertSame($sent ? 'success' : 'danger', session('alert')['type']);
            $this->assertSame(lang($sent ? 'Admin.userInviteQueued' : 'Admin.userInviteFailed'), session('alert')['message']);
            Services::resetSingle('email');
        }
    }

    public function testInviteUsesSavedMailTemplate(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        service('settings')->set('Email.fromEmail', 'sender@example.com');
        service('settings')->set(MailTemplates::settingKey('invitation', 'en', 'subject'), 'Welcome {username}');
        service('settings')->set(MailTemplates::settingKey('invitation', 'en', 'body'), 'Open {link}');

        $email = $this->createMock(QueuedEmail::class);
        $email->expects($this->once())->method('setSubject')->with('Welcome invitee');
        $email->expects($this->once())->method('setMailType')->with('html');
        $email->expects($this->once())->method('setMessage')->with($this->callback(static fn (string $body): bool => str_contains($body, '<body>Open ' . url_to('magic-link') . '</body>')));
        $email->method('send')->willReturn(true);
        Services::injectMock('email', $email);

        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
    }

    public function testInvitationQueueAuditShowsLatestDeliveryStatus(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        service('settings')->set('Email.fromEmail', 'sender@example.com');

        $this->assertStringContainsString('Not sent', $this->get('/en/admin/users?q=invitee')->response()->getBody());
        Services::injectMock('email', service('email', null, false));

        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->exactly(2))->method('push')->with('email', 'send-email', $this->callback(static fn (array $data): bool => $data['to'] === ['invitee@example.com']
            && $data['subject'] === lang('Admin.userInviteSubject')
            && $data['type'] === 'html'
            && str_contains($data['body'], '<!DOCTYPE html PUBLIC')
            && ! str_contains($data['body'], 'GeminusAdmin')))
            ->willReturnOnConsecutiveCalls(QueuePushResult::success(41), QueuePushResult::success(42));
        Services::injectMock('queue', $queue);

        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(lang('Admin.userInviteQueued'), session('alert')['message']);
        $log = db_connect()->table('email_delivery_logs')->where('invited_user_id', $user->id)->get()->getRowArray();
        $this->assertSame('queued', $log['status']);
        $this->assertStringContainsString('Queued', $this->get('/en/admin/users?q=invitee')->response()->getBody());

        db_connect()->table('email_delivery_logs')->where('id', $log['id'])->update(['status' => 'sent']);
        $this->assertStringContainsString('Sent', $this->get('/en/admin/users?q=invitee')->response()->getBody());

        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertStringContainsString('Queued', $this->get('/en/admin/users?q=invitee')->response()->getBody());
        $latest = db_connect()->table('email_delivery_logs')->where('invited_user_id', $user->id)->orderBy('id', 'DESC')->get()->getRowArray();
        db_connect()->table('email_delivery_logs')->where('id', $latest['id'])->update(['status' => 'failed']);
        $this->assertStringContainsString('Failed', $this->get('/en/admin/users?q=invitee')->response()->getBody());
    }

    public function testInviteRejectsUnauthorizedOrUnavailableAccounts(): void
    {
        $this->loginAs('admin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();

        auth()->logout();
        $this->loginAs('superadmin');
        $this->post('/en/admin/users/' . auth()->id() . '/invite', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->post('/en/admin/users/999999/invite', [csrf_token() => csrf_hash()])->assertStatus(404);
        service('settings')->set('Email.fromEmail', '');
        $this->post('/en/admin/users/' . $user->id . '/invite', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(lang('Admin.inviteUnavailable'), session('alert')['message']);
    }

    public function testInviteRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('invitee', 'invitee@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'invitee@example.com']);
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/users/' . $user->id . '/invite', []);
    }

    public function testImportReportDoesNotRenderMissingReasonKey(): void
    {
        $this->loginAs('superadmin');
        session()->setFlashdata('user_import_report', [
            ['row' => 2, 'email' => 'created@example.com', 'result' => 'created', 'reason' => ''],
            ['row' => 3, 'email' => 'created@example.com', 'result' => 'skipped', 'reason' => 'duplicate'],
        ]);

        $page = $this->withSession($_SESSION)->get('/en/admin/users');
        $page->assertOK();
        $this->assertStringContainsString('Email already exists.', $page->response()->getBody());
        $this->assertStringNotContainsString('Admin.userReason_', $page->response()->getBody());
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'userstest' . $group]);
        $user->email = 'userstest' . $group . '@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }

    private function createUser(string $username, string $email): void
    {
        $user        = new AdminUser(['username' => $username]);
        $user->email = $email;
        $user->setPassword('A-local-password-123!');
        auth()->getProvider()->save($user);
    }
}
