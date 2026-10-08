<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\AuthGroups;
use Config\Services;
use Geminus\Admin\Config\AdminMenu;
use Geminus\Admin\Entities\AdminUser;
use Modules\Announcements\Config\Registrar;
use Modules\Announcements\Database\Migrations\GrantAnnouncementsToSuperadmin;
use Modules\Announcements\Models\AnnouncementModel;

/**
 * @internal
 */
final class AnnouncementsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testAccessRequiresPermission(): void
    {
        $this->get('/en/admin/announcements')->assertRedirect();
        $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => 'Hidden', 'body' => 'Hidden'])->assertRedirect();
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());

        $this->loginAs('admin');
        $this->get('/en/admin/announcements')->assertRedirect();
        $this->get('/en/admin/announcements/create')->assertRedirect();
        $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => 'Hidden', 'body' => 'Hidden'])->assertRedirect();
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        $dashboard = $this->get('/en/admin/dashboard');
        $dashboard->assertOK();
        $this->assertStringNotContainsString('href="/en/admin/announcements"', $dashboard->response()->getBody());
    }

    public function testCreateRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/announcements/create', ['title' => 'No token', 'body' => 'Rejected']);
    }

    public function testModulePersistsPermissionAndRegistersMenu(): void
    {
        $this->assertArrayNotHasKey('announcements.manage', (new ReflectionClass(AuthGroups::class))->getDefaultProperties()['permissions']);
        $this->assertArrayNotHasKey('announcements.manage', (new AuthGroups())->permissions);
        $this->assertSame([], (new ReflectionClass(AdminMenu::class))->getDefaultProperties()['items']);
        $this->assertContains(Registrar::AdminMenu()['items'][0], config(AdminMenu::class)->items);
    }

    public function testExistingPermissionMatrixReceivesNewGrantWithoutChangingOtherRoles(): void
    {
        Services::resetSingle('settings');
        $original             = setting('AuthGroups.matrix');
        $originalPermissions  = setting('AuthGroups.permissions');
        $matrix               = $original;
        $matrix['superadmin'] = ['admin.*', 'users.*'];
        setting('AuthGroups.matrix', $matrix);
        service('settings')->forget('AuthGroups.permissions');

        try {
            $settings          = config('Settings')->database;
            $storedPermissions = db_connect($settings['group'])->table($settings['table'])->where('class', AuthGroups::class)->where('key', 'permissions');
            $this->assertSame(0, $storedPermissions->countAllResults());

            $migration = new GrantAnnouncementsToSuperadmin();
            $migration->up();
            $migration->up();

            Services::resetSingle('settings');
            $updated = setting('AuthGroups.matrix');
            $this->assertSame(['admin.*', 'users.*', 'announcements.manage'], $updated['superadmin']);
            $this->assertSame($matrix['admin'], $updated['admin']);
            $this->assertSame('Can manage example announcements', setting('AuthGroups.permissions')['announcements.manage']);
            $this->assertSame(1, db_connect($settings['group'])->table($settings['table'])->where('class', AuthGroups::class)->where('key', 'permissions')->countAllResults());

            $permissions                         = setting('AuthGroups.permissions');
            $permissions['announcements.manage'] = 'Custom description';
            setting('AuthGroups.permissions', $permissions);
            Services::resetSingle('settings');
            $migration->up();
            Services::resetSingle('settings');
            $this->assertSame('Custom description', setting('AuthGroups.permissions')['announcements.manage']);
        } finally {
            setting('AuthGroups.matrix', $original);
            setting('AuthGroups.permissions', $originalPermissions);
        }
    }

    public function testValidationRejectsMissingFields(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => '', 'body' => ''])->assertRedirectTo('/en/admin/announcements/create');
        $this->assertNotEmpty(session('errors.title'));
        $this->assertNotEmpty(session('errors.body'));
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testValidationRejectsOverlongFields(): void
    {
        $this->loginAs('superadmin');

        foreach ([['title' => str_repeat('A', 151), 'body' => 'Valid'], ['title' => 'Valid', 'body' => str_repeat('B', 2001)]] as $data) {
            $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash()] + $data)->assertRedirectTo('/en/admin/announcements/create');
        }
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
    }

    public function testCreateAndListEscapesContent(): void
    {
        $this->loginAs('superadmin');
        $create = $this->get('/en/admin/announcements/create');
        $create->assertOK();
        $create->assertSee('New announcement');
        $this->post('/en/admin/announcements/create', [
            csrf_token() => csrf_hash(),
            'title'      => '<script>alert(1)</script>',
            'body'       => 'Example body',
            'ignored'    => 'not stored',
        ])->assertRedirectTo('/en/admin/announcements');

        $saved = (new AnnouncementModel())->first();
        $this->assertSame('<script>alert(1)</script>', $saved['title']);
        $this->assertSame('Example body', $saved['body']);
        $this->assertSame(1, (new AnnouncementModel())->countAllResults());
        $page = $this->get('/en/admin/announcements');
        $page->assertOK();
        $page->assertSee('Example body');
        $this->assertStringContainsString('href="/en/admin/announcements"', $page->response()->getBody());
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page->response()->getBody());
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page->response()->getBody());
        $this->assertStringContainsString('href="/en/admin/announcements/create"', $page->response()->getBody());
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'example' . $group]);
        $user->email = 'example@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }
}
