<?php

declare(strict_types=1);

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Filters\OperationAudit;

/**
 * @internal
 */
final class OperationAuditTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testLoginFailureIsNotRecordedInOperationAudit(): void
    {
        $this->post('/en/login', [
            csrf_token() => csrf_hash(),
            'email'      => 'nobody@example.com',
            'password'   => 'secret-do-not-log',
        ])->assertRedirect();

        $this->assertSame(1, db_connect()->table('auth_logins')->where('identifier', 'nobody@example.com')->where('success', 0)->countAllResults());
        $this->assertSame(0, db_connect()->table('operation_audit_logs')->countAllResults());
    }

    public function testMagicLinkFailureIsNotRecordedInOperationAudit(): void
    {
        $this->get('/en/login/verify-magic-link?token=secret-do-not-log')->assertRedirect();

        $this->assertSame(0, db_connect()->table('operation_audit_logs')->countAllResults());
    }

    public function testAuthenticatedWriteCapturesActorAndTargetWithoutFormData(): void
    {
        $user        = new AdminUser(['username' => 'auditadmin']);
        $user->email = 'auditadmin@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);
        $actorId = auth()->id();
        $this->get('/en/admin/users')->assertOK();

        $this->post('/en/admin/users/987/invite', [
            csrf_token() => csrf_hash(),
            'email'      => 'private@example.com',
        ]);

        $logs = db_connect()->table('operation_audit_logs')->get()->getResultArray();
        $this->assertCount(1, $logs);
        $this->assertSame((int) $actorId, (int) $logs[0]['actor_id']);
        $this->assertSame('POST', $logs[0]['action']);
        $this->assertSame('users', $logs[0]['target_type']);
        $this->assertSame('987', $logs[0]['target_id']);
        $this->assertSame('failed', $logs[0]['result']);
        $this->assertStringNotContainsString('private@example.com', json_encode($logs[0]));
    }

    public function testRedirectsWithValidationErrorsAndSuccessAlertHaveDifferentResults(): void
    {
        $user        = new AdminUser(['username' => 'auditredirect']);
        $user->email = 'auditredirect@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => '',
            'language'   => 'en',
            'timezone'   => 'Asia/Shanghai',
        ])->assertRedirect();

        $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'auditredirect',
            'language'   => 'en',
            'timezone'   => 'Asia/Shanghai',
        ])->assertRedirect();

        $logs = db_connect()->table('operation_audit_logs')->orderBy('id')->get()->getResultArray();
        $this->assertSame(['failed', 'success'], array_column($logs, 'result'));
    }

    public function testDangerAlertMarksRedirectAsFailed(): void
    {
        $user        = new AdminUser(['username' => 'auditdanger']);
        $user->email = 'auditdanger@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $this->post('/en/admin/profile/language', [
            csrf_token() => csrf_hash(),
            'language'   => 'not-supported',
        ])->assertRedirect();

        $log = db_connect()->table('operation_audit_logs')->get()->getRowArray();
        $this->assertSame('failed', $log['result']);
    }

    public function testPreviousFlashMessageDoesNotClassifyNewRedirect(): void
    {
        $user        = new AdminUser(['username' => 'auditprevious']);
        $user->email = 'auditprevious@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        session()->setFlashdata('alert', ['type' => 'success', 'message' => 'Previous request']);
        $request = $this->setupRequest('POST', '/en/admin/profile/language');
        $audit   = new OperationAudit();
        $audit->before($request);
        $audit->after($request, service('response')->setStatusCode(302));

        $log = db_connect()->table('operation_audit_logs')->get()->getRowArray();
        $this->assertSame('redirected', $log['result']);

        $audit->before($request);
        session()->setFlashdata('alert', ['type' => 'danger', 'message' => 'Current request']);
        $audit->after($request, service('response')->setStatusCode(302));

        $current = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertSame('failed', $current['result']);
    }

    public function testRedirectFailureFeedbackTakesPrecedenceOverSuccessAlert(): void
    {
        $user        = new AdminUser(['username' => 'auditpriority']);
        $user->email = 'auditpriority@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $request = $this->setupRequest('POST', '/en/admin/profile');
        $audit   = new OperationAudit();
        $audit->before($request);
        session()->setFlashdata('alert', ['type' => 'success', 'message' => 'Partial operation']);
        session()->setFlashdata('profile_errors', ['username' => 'Rejected']);
        $audit->after($request, service('response')->setStatusCode(302));

        $log = db_connect()->table('operation_audit_logs')->get()->getRowArray();
        $this->assertSame('failed', $log['result']);
    }

    public function testUnauthenticatedWriteIsNotAttributedToAnActor(): void
    {
        $this->post('/en/admin/profile/language', [
            csrf_token() => csrf_hash(),
            'language'   => 'en',
        ])->assertRedirect();

        $this->assertSame(0, db_connect()->table('operation_audit_logs')->countAllResults());
    }

    public function testPermissionFilterRejectionDoesNotProduceAnAuditRow(): void
    {
        $user        = new AdminUser(['username' => 'auditrestricted']);
        $user->email = 'auditrestricted@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $this->post('/en/admin/users/42/invite', [csrf_token() => csrf_hash()])->assertRedirect();

        $this->assertSame(0, db_connect()->table('operation_audit_logs')->countAllResults());
    }

    public function testSuccessfulLoginIsNotRecordedInOperationAudit(): void
    {
        $user        = new AdminUser(['username' => 'auditlogin']);
        $user->email = 'auditlogin@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $this->post('/en/login', [
            csrf_token() => csrf_hash(),
            'email'      => 'auditlogin@example.com',
            'password'   => 'A-local-password-123!',
        ])->assertRedirect();

        $this->assertTrue(auth()->loggedIn());
        $this->assertSame(1, db_connect()->table('auth_logins')->where('user_id', auth()->id())->where('success', 1)->countAllResults());
        $this->assertSame(0, db_connect()->table('operation_audit_logs')->countAllResults());
    }

    public function testAlreadyAuthenticatedLoginRequestIsNotRecordedInOperationAudit(): void
    {
        $user        = new AdminUser(['username' => 'auditalready']);
        $user->email = 'auditalready@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $request = $this->setupRequest('POST', '/en/login');
        $audit   = new OperationAudit();
        $audit->before($request);
        $audit->after($request, service('response')->setStatusCode(302));

        $this->assertSame(0, db_connect()->table('operation_audit_logs')->countAllResults());
    }

    public function testMicrosoftBindingCallbackIsNotCountedAsLogin(): void
    {
        $user        = new AdminUser(['username' => 'auditbinding']);
        $user->email = 'auditbinding@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));
        session()->set('microsoft_flow', ['mode' => 'bind']);

        $this->get('/en/microsoft/callback')->assertRedirect();

        $this->assertSame(0, db_connect()->table('operation_audit_logs')->countAllResults());
    }

    public function testAuditPageRequiresPermissionAndFiltersByActorTargetDateAndResult(): void
    {
        $this->get('/en/admin/audit')->assertRedirect();

        db_connect()->table('operation_audit_logs')->insert([
            'actor_id'   => null, 'action' => 'POST', 'target_type' => 'users',
            'target_id'  => '987', 'path' => 'restricted-audit-marker', 'result' => 'failed',
            'ip_address' => '127.0.0.1', 'created_at' => '2026-10-07 12:00:00',
        ]);

        $user        = new AdminUser(['username' => 'auditviewer']);
        $user->email = 'auditviewer@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);
        $denied = $this->get('/en/admin/audit');
        $denied->assertRedirect();
        $denied->assertDontSee('restricted-audit-marker');
        $user->addGroup('superadmin');

        foreach ([
            ['actor_id' => $user->id, 'target_id' => '42', 'result' => 'failed', 'created_at' => '2026-10-08 12:00:00'],
            ['actor_id' => $user->id, 'target_id' => '43', 'result' => 'success', 'created_at' => '2026-10-09 12:00:00'],
        ] as $log) {
            db_connect()->table('operation_audit_logs')->insert($log + [
                'action' => 'POST', 'target_type' => 'users', 'path' => 'en/admin/users', 'ip_address' => '127.0.0.1',
            ]);
        }

        $page = $this->get('/en/admin/audit?actor=' . $user->id . '&type=users&target=42&result=failed&from=2026-10-08&to=2026-10-08');
        $page->assertOK();
        $page->assertSee('users #42');
        $page->assertDontSee('users #43');
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 1', $page->response()->getBody());
        $this->assertStringContainsString('private, no-store', $page->response()->getHeaderLine('Cache-Control'));
    }

    public function testEachAuditFilterCanExcludeRecordsIndependently(): void
    {
        $user        = new AdminUser(['username' => 'auditfilters']);
        $user->email = 'auditfilters@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        $base = [
            'actor_id' => $user->id, 'target_id' => '42', 'target_type' => 'users',
            'result'   => 'failed', 'created_at' => '2026-10-08 23:59:59',
            'action'   => 'POST', 'path' => 'en/admin/users/42', 'ip_address' => '127.0.0.1',
        ];
        $variants = [
            ['actor_id' => 999999, 'path' => 'actor-marker'],
            ['target_id' => '43', 'path' => 'target-marker'],
            ['target_type' => 'profile', 'path' => 'type-marker'],
            ['result' => 'success', 'path' => 'result-marker'],
            ['created_at' => '2026-10-07 23:59:59', 'path' => 'from-marker'],
            ['created_at' => '2026-10-09 00:00:00', 'path' => 'to-marker'],
        ];
        db_connect()->table('operation_audit_logs')->insert($base);

        foreach ($variants as $variant) {
            db_connect()->table('operation_audit_logs')->insert($variant + $base);
        }

        foreach ([
            'actor=' . $user->id => 'actor-marker',
            'target=42'          => 'target-marker',
            'type=users'         => 'type-marker',
            'result=failed'      => 'result-marker',
            'from=2026-10-08'    => 'from-marker',
            'to=2026-10-08'      => 'to-marker',
        ] as $filter => $excluded) {
            $page = $this->get('/en/admin/audit?' . $filter);
            $page->assertOK();
            $page->assertSee('en/admin/users/42');
            $page->assertDontSee($excluded);
            $this->assertStringContainsString(lang('Admin.userTotal') . ': 6', $page->response()->getBody());
        }
    }

    public function testLongNumericTargetIsTruncatedBeforeInsert(): void
    {
        $user        = new AdminUser(['username' => 'auditlong']);
        $user->email = 'auditlong@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        $request = $this->setupRequest('POST', '/en/admin/users/' . str_repeat('9', 40) . '/invite');
        (new OperationAudit())->after($request, service('response'));

        $log = db_connect()->table('operation_audit_logs')->get()->getRowArray();
        $this->assertNotNull($log);
        $this->assertSame(str_repeat('9', 32), $log['target_id']);
    }

    public function testWriteMethodAndResponseStatusDetermineAuditResult(): void
    {
        $user        = new AdminUser(['username' => 'auditmethods']);
        $user->email = 'auditmethods@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        foreach ([
            ['PUT', 'en/admin/profile/language', 200, 'success', null, 'profile'],
            ['PATCH', 'en/admin/users/5', 302, 'redirected', '5', 'users'],
            ['DELETE', 'en/admin/users/6', 500, 'failed', '6', 'users'],
            ['POST', 'en/admin/profile/tokens/5/revoke', 200, 'success', '5', 'tokens'],
            ['POST', 'en/admin/settings/microsoft/requests/3/approve', 200, 'success', '3', 'requests'],
            ['POST', 'en/admin/settings/roles/editor/permissions', 200, 'success', 'editor', 'roles'],
            ['POST', 'en/admin/settings/permissions/users.edit', 200, 'success', 'users.edit', 'permissions'],
            ['POST', 'en/admin/mail/templates/invitation/en', 200, 'success', 'invitation', 'templates'],
        ] as [$method, $path, $status, $result, $targetId, $type]) {
            $request = $this->setupRequest($method, '/' . $path);
            (new OperationAudit())->after($request, service('response')->setStatusCode($status));
            $log = db_connect()->table('operation_audit_logs')->where('path', $path)->get()->getRowArray();
            $this->assertSame($method, $log['action']);
            $this->assertSame($result, $log['result']);
            $this->assertSame($targetId, $log['target_id']);
            $this->assertSame($type, $log['target_type']);
        }
    }

    public function testAuditPaginationClampsPageAndIgnoresInvalidFilters(): void
    {
        $user        = new AdminUser(['username' => 'auditpages']);
        $user->email = 'auditpages@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        for ($number = 1; $number <= 21; $number++) {
            db_connect()->table('operation_audit_logs')->insert([
                'actor_id'  => $user->id, 'action' => 'POST', 'target_type' => 'users',
                'target_id' => (string) $number, 'path' => 'audit-record-' . $number,
                'result'    => 'failed', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-08 12:00:00',
            ]);
        }

        $first = $this->get('/en/admin/audit?page=0');
        $first->assertSee('audit-record-21');
        $first->assertDontSee('audit-record-1</td>');
        $last = $this->get('/en/admin/audit?page=999');
        $last->assertSee('audit-record-1');
        $last->assertDontSee('audit-record-21</td>');
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 21', $last->response()->getBody());

        $invalid = $this->get('/en/admin/audit?result=other&from=2026-02-30&to=2026-02-30');
        $invalid->assertSee('audit-record-21');
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 21', $invalid->response()->getBody());

        $largeActor = $this->get('/en/admin/audit?actor=99999999999999999999');
        $largeActor->assertOK();
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 21', $largeActor->response()->getBody());

        db_connect()->table('operation_audit_logs')->insert([
            'actor_id'   => $user->id, 'action' => 'POST', 'target_type' => 'users',
            'target_id'  => '99', 'path' => 'audit-success-marker', 'result' => 'success',
            'ip_address' => '127.0.0.1', 'created_at' => '2026-10-08 12:00:00',
        ]);
        $filtered = $this->get('/en/admin/audit?result=failed');
        $filtered->assertDontSee('audit-success-marker');
        $this->assertStringContainsString('result=failed', $filtered->response()->getBody());
        $second = $this->get('/en/admin/audit?result=failed&page=2');
        $second->assertSee('audit-record-1');
        $second->assertDontSee('audit-success-marker');
    }

    public function testAuditPageFiltersStringObjectName(): void
    {
        $user        = new AdminUser(['username' => 'auditslug']);
        $user->email = 'auditslug@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        foreach (['editor', 'reader'] as $name) {
            db_connect()->table('operation_audit_logs')->insert([
                'actor_id'  => $user->id, 'action' => 'POST', 'target_type' => 'roles',
                'target_id' => $name, 'path' => 'en/admin/settings/roles/' . $name,
                'result'    => 'redirected', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-08 12:00:00',
            ]);
        }

        $page = $this->get('/en/admin/audit?type=roles&target=editor');
        $page->assertSee('roles #editor');
        $page->assertDontSee('roles #reader');
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 1', $page->response()->getBody());
    }

    public function testAuditPageEscapesUntrustedStoredPath(): void
    {
        $user        = new AdminUser(['username' => 'auditescape']);
        $user->email = 'auditescape@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        db_connect()->table('operation_audit_logs')->insert([
            'actor_id'  => $user->id, 'action' => 'POST', 'target_type' => 'roles',
            'target_id' => '<script>alert(1)</script>', 'path' => '<script>alert(1)</script>',
            'result'    => 'failed', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-08 12:00:00',
        ]);

        $body = $this->get('/en/admin/audit')->response()->getBody();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function testAuditStorageFailureDoesNotChangeBusinessResponse(): void
    {
        $user        = new AdminUser(['username' => 'auditfailure']);
        $user->email = 'auditfailure@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $uri     = $this->setupRequest('POST', '/en/admin/profile/language')->getUri();
        $request = $this->createStub(RequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getUri')->willReturn($uri);
        $request->method('getIPAddress')->willReturn(str_repeat('1', 46));
        $response = service('response')->setStatusCode(302);

        $this->assertNull((new OperationAudit())->after($request, $response));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(0, db_connect()->table('operation_audit_logs')->countAllResults());
    }
}
