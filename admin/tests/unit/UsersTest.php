<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\MicrosoftLinks;

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
        $this->assertStringContainsString('Your account', $body);
        $this->assertStringContainsString('Protected account', $body);
        $this->assertStringContainsString('Enabled', $body);

        $empty = $this->get('/en/admin/users?q=no-such-user');
        $empty->assertOK();
        $this->assertStringContainsString('No users found.', $empty->response()->getBody());
        $this->assertStringContainsString('Clear', $empty->response()->getBody());
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

    public function testEditingUsernameDetectsLegacyCaseCollisionAfterExcludingSelf(): void
    {
        $this->loginAs('superadmin');
        $this->createUser('target', 'target@example.com');
        $user = auth()->getProvider()->findByCredentials(['email' => 'target@example.com']);
        $this->createUser('TARGET', 'legacycase@example.com');

        $this->post('/en/admin/users/' . $user->id . '/edit', [
            csrf_token() => csrf_hash(),
            'username'   => 'Target',
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
            'username'   => 'target',
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
