<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Entities\AdminUser;

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
        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'role' => 'admin', 'status' => 'banned'])->assertRedirect();
        $updated = auth()->getProvider()->findById($user->id);
        $this->assertTrue($updated->inGroup('admin'));
        $this->assertTrue($updated->isBanned());
        $this->get('/en/admin/users/' . auth()->id() . '/edit')->assertStatus(404);
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
        $this->post('/en/admin/users/' . $user->id . '/edit', [csrf_token() => csrf_hash(), 'role' => 'superadmin', 'status' => 'enabled'])->assertRedirect();
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
        $this->get('/en/admin/users/create')->assertOK();
        $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'createduser', 'email' => 'created@example.com'])->assertRedirect();
        $user = auth()->getProvider()->findByCredentials(['email' => 'created@example.com']);
        $this->assertNotNull($user);
        $this->assertTrue($user->inGroup('user'));
        $this->assertFalse($user->can('admin.access'));

        $this->post('/en/admin/users/create', [csrf_token() => csrf_hash(), 'username' => 'otheruser', 'email' => 'CREATED@example.com'])->assertRedirect();
        $this->assertSame(lang('Admin.userReason_duplicate'), session('user_errors.email'));
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
