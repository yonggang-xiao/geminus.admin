<?php

declare(strict_types=1);

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Filters\OperationAudit;
use Geminus\Admin\Models\AttachmentModel;
use Modules\Announcements\Controllers\AnnouncementAttachments;
use Modules\Announcements\Models\AnnouncementModel;
use Tests\Support\Libraries\TableLayoutAssertions;

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

    public function testConfiguredModulesCaptureOnlyDeclaredSubmissionFields(): void
    {
        $user        = new AdminUser(['username' => 'auditmodules']);
        $user->email = 'auditmodules@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        $this->post('/zh-Hans/admin/profile/language', [csrf_token() => csrf_hash(), 'language' => 'zh-Hans', 'password' => 'private-password'])->assertRedirect();
        $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => 'Audit example', 'body' => 'private-body', 'actor_id' => 999])->assertRedirect();
        $announcement = (new AnnouncementModel())->first();
        $this->post('/zh-Hant/admin/announcements/' . $announcement['id'] . '/edit', [csrf_token() => csrf_hash(), 'title' => '<script>alert("audit")</script>', 'body' => 'private-body'])->assertRedirect();

        $logs = db_connect()->table('operation_audit_logs')->orderBy('id')->get()->getResultArray();
        $this->assertSame(['profile.language', 'announcement.create', 'announcement.update'], array_column($logs, 'operation'));
        $this->assertSame(['profile', 'announcement', 'announcement'], array_column($logs, 'target_type'));
        $this->assertNull($logs[1]['target_id']);
        $this->assertSame((string) $announcement['id'], $logs[2]['target_id']);
        $this->assertSame(['language' => 'zh-Hans'], json_decode($logs[0]['submission'], true)['fields']);
        $this->assertSame(['title' => 'Audit example'], json_decode($logs[1]['submission'], true)['fields']);
        $this->assertStringNotContainsString('private-', json_encode($logs));
        $this->assertSame((int) $user->id, (int) $logs[1]['actor_id']);

        $page = $this->get('/zh-Hans/admin/audit');
        $page->assertOK();
        $page->assertSee('announcement.update');
        $page->assertSee('提交内容');
        $this->assertStringNotContainsString('<script>alert("audit")</script>', $page->response()->getBody());
        $this->assertStringContainsString('&lt;script&gt;', $page->response()->getBody());

        $this->post('/en/admin/announcements/' . $announcement['id'] . '/publish', [csrf_token() => csrf_hash()])->assertRedirect();
        $published = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertNull($published['operation']);
        $this->assertSame((string) $announcement['id'], $published['target_id']);
        $this->assertNull($published['submission']);

        $this->post('/en/admin/profile/language', [csrf_token() => csrf_hash(), 'language' => 'invalid'])->assertRedirect();
        $failed = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertSame('failed', $failed['result']);
        $this->assertSame(['language' => 'invalid'], json_decode($failed['submission'], true)['fields']);
        $this->assertSame('zh-Hans', $users->findById($user->id)->language);
    }

    public function testAuditDisplaysViewerTimezoneButFiltersUtcDates(): void
    {
        $users       = auth()->getProvider();
        $user        = new AdminUser(['username' => 'audit-timezone', 'timezone' => 'Asia/Shanghai']);
        $user->email = 'audit-timezone@example.com';
        $user->setPassword('A-local-password-123!');
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);
        db_connect()->table('operation_audit_logs')->insert([
            'actor_id'  => $user->id, 'action' => 'POST', 'target_type' => 'users',
            'target_id' => (string) $user->id, 'path' => 'utc-boundary-marker',
            'result'    => 'success', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-08 23:30:00',
        ]);
        $page = $this->get('/en/admin/audit?from=2026-10-08&to=2026-10-08');
        $page->assertOK();
        $page->assertSee('utc-boundary-marker');
        $page->assertSee('2026-10-09 07:30:00', 'td');
        $nextDay = $this->get('/en/admin/audit?from=2026-10-09&to=2026-10-09');
        $nextDay->assertOK();
        $nextDay->assertDontSee('utc-boundary-marker');
        $this->assertSame('2026-10-08 23:30:00', db_connect()->table('operation_audit_logs')->get()->getRow('created_at'));
    }

    public function testAttachmentUploadCapturesMetadataWithoutContentOrStoragePath(): void
    {
        $user        = new AdminUser(['username' => 'auditattachment']);
        $user->email = 'auditattachment@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);
        $owner  = (int) (new AnnouncementModel())->insert(['title' => 'Attachment audit', 'body' => 'Body']);
        $route  = '/en/admin/announcements/' . $owner . '/attachments';
        $source = tempnam(sys_get_temp_dir(), 'audit-upload-');
        file_put_contents($source, 'private-file-content');
        $storedPath = null;

        try {
            foreach ([
                ['<script>.txt', 'success', UPLOAD_ERR_OK],
                ['rejected.exe', 'failed', UPLOAD_ERR_OK],
                ['unselected.txt', 'failed', UPLOAD_ERR_NO_FILE],
                ['oversized.txt', 'failed', UPLOAD_ERR_INI_SIZE],
            ] as [$name, $expectedResult, $uploadError]) {
                session()->remove(['alert', 'attachment_errors']);
                $flashState = session()->get('__ci_vars') ?? [];
                unset($flashState['alert'], $flashState['attachment_errors']);
                session()->set('__ci_vars', $flashState);
                $file = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$source, $name, 'text/client-declared', filesize($source), $uploadError])
                    ->onlyMethods(['isValid', 'move'])->getMock();
                $file->method('isValid')->willReturn($uploadError === UPLOAD_ERR_OK);
                if ($expectedResult === 'success') {
                    $file->expects($this->once())->method('move')->willReturnCallback(static function (string $directory, ?string $filename) use ($source, &$storedPath): bool {
                        $storedPath = $directory . '/' . $filename;

                        return copy($source, $storedPath);
                    });
                } else {
                    $file->expects($this->never())->method('move');
                }
                $base    = $this->setupRequest('POST', $route);
                $request = $this->getMockBuilder(IncomingRequest::class)
                    ->setConstructorArgs([config('App'), $base->getUri(), null, new UserAgent()])
                    ->onlyMethods(['getFile'])->getMock();
                $request->setMethod('POST');
                $request->setHeader('Content-Type', 'multipart/form-data; boundary=audit-test');
                $request->expects($this->exactly(4))->method('getFile')->with('file')->willReturn($file);
                $routes = service('routes');
                $routes->loadRoutes();
                $router = Services::router($routes, $request, false);
                $router->handle(ltrim($route, '/'));
                Services::injectMock('router', $router);
                $audit = new OperationAudit();
                $audit->before($request);
                $controller = new AnnouncementAttachments();
                $controller->initController($request, Services::response(null, false), service('logger'));
                $response = $controller->upload($owner);
                $audit->after($request, $response);
                $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
                $this->assertSame('announcement.attachment.upload', $log['operation']);
                $this->assertSame('announcements', $log['target_type']);
                $this->assertSame((string) $owner, $log['target_id']);
                $this->assertSame($expectedResult, $log['result']);
                $fields = json_decode($log['submission'], true)['fields'];
                ksort($fields);
                $this->assertSame($uploadError === UPLOAD_ERR_OK ? ['file.name' => $name, 'file.size' => strlen('private-file-content'), 'file.type' => 'text/client-declared'] : [], $fields);
                $this->assertStringNotContainsString('private-file-content', $log['submission']);
                $this->assertStringNotContainsString($source, $log['submission']);
                $this->assertStringNotContainsString($storedPath, $log['submission']);
                $request->setHeader('Content-Type', 'application/json');
                $request->setBody('{"file.name":"forged","file.size":999,"file.type":"forged"}');
                $audit->before($request);
                $audit->after($request, Services::response(null, false)->setStatusCode(400));
                $forged        = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
                $forgedSummary = json_decode($forged['submission'], true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame([], $forgedSummary['fields']);
                $this->assertArrayNotHasKey('input', $forgedSummary);
            }
            $this->assertSame(1, (new AttachmentModel())->countAllResults());
            $page = $this->get('/en/admin/audit');
            $page->assertOK();
            $this->assertStringContainsString('&lt;script&gt;.txt', $page->response()->getBody());
            $this->assertStringNotContainsString('<script>.txt', $page->response()->getBody());
            $this->post($route, [csrf_token() => csrf_hash(), 'file.name' => 'forged', 'file.size' => 999, 'file.type' => 'forged'])->assertRedirect();
            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('failed', $log['result']);
            $this->assertSame([], json_decode($log['submission'], true)['fields']);
        } finally {
            unlink($source);
            if ($storedPath !== null && is_file($storedPath)) {
                unlink($storedPath);
            }
        }
    }

    public function testAdminBuiltInWritesCaptureOnlySafeNonEmptyFields(): void
    {
        foreach (config(Geminus\Admin\Config\OperationAudit::class)->operations as $definition) {
            [$controller, $method] = explode('::', $definition['controller']);
            $this->assertTrue(method_exists($controller, $method));
        }
        $users        = auth()->getProvider();
        $actor        = new AdminUser(['username' => 'auditadmin']);
        $actor->email = 'auditadmin@example.com';
        $actor->setPassword('A-local-password-123!');
        $users->save($actor);
        $actor = $users->findById($users->getInsertID());
        $actor->addGroup('superadmin');
        $target        = new AdminUser(['username' => 'audittarget']);
        $target->email = 'audittarget@example.com';
        $target->setPassword('A-local-password-123!');
        $users->save($target);
        $target = $users->findById($users->getInsertID());
        auth()->login($actor);
        $originalSettings = service('settings')->getMany([
            'AuthGroups.groups', 'AuthGroups.permissions', 'AuthGroups.matrix',
            'MicrosoftOAuth.enabled', 'MicrosoftOAuth.tenant', 'MicrosoftOAuth.clientId',
            'MailTemplates.invitation_zh_Hans_subject', 'MailTemplates.invitation_zh_Hans_body',
        ]);
        $template = service('mailTemplates')->get('invitation', 'zh-Hans');
        $cases    = [
            ['/en/admin/settings/roles', ['name' => 'audit-role', 'title' => 'Audit role', 'description' => 'Role metadata'], 'role.create', 'roles', null, 'success'],
            ['/zh-Hans/admin/settings/roles/audit-role', ['title' => 'Updated role', 'description' => 'Updated metadata'], 'role.update', 'roles', 'audit-role', 'success'],
            ['/zh-Hant/admin/settings/permissions', ['name' => 'audit.read', 'description' => 'Audit permission'], 'permission.create', 'permissions', null, 'success'],
            ['/en/admin/settings/permissions/audit.read', ['description' => 'Updated permission'], 'permission.update', 'permissions', 'audit.read', 'success'],
            ['/en/admin/users/' . $target->id . '/edit', ['role' => 'user', 'status' => 'enabled'], 'user.update', 'users', (string) $target->id, 'success'],
            ['/en/admin/mail/templates/invitation/zh-Hans', ['subject' => 'Audit invitation'], 'email.template.update', 'templates', 'invitation', 'success'],
            ['/en/admin/settings/microsoft', ['enabled' => '1'], 'microsoft.settings.update', 'microsoft_settings', null, 'success'],
            ['/en/admin/settings/microsoft', ['enabled' => '0'], 'microsoft.settings.update', 'microsoft_settings', null, 'success'],
            ['/en/admin/settings/microsoft', [], 'microsoft.settings.update', 'microsoft_settings', null, 'success'],
            ['/en/admin/settings/microsoft/requests/42/approve', ['user_id' => '999999999'], 'microsoft.link.approve', 'requests', '42', 'failed'],
            ['/en/admin/profile/tokens', ['name' => 'Audit token', 'expires' => '2099-01-01'], 'profile.token.create', 'tokens', null, 'redirected'],
            ['/en/admin/profile/tokens', ['name' => 'Expired token', 'expires' => '2000-01-01'], 'profile.token.create', 'tokens', null, 'failed'],
        ];

        try {
            foreach ($cases as [$path, $fields, $operation, $type, $targetId, $result]) {
                $input = $fields + [
                    csrf_token() => csrf_hash(), 'username' => $target->username, 'email' => $target->email,
                    'body'       => $template['body'], 'tenant' => 'organizations', 'clientId' => '11111111-2222-3333-4444-555555555555',
                    'password'   => 'private-secret', 'clientSecret' => 'private-secret', 'actor_id' => 999, 'id' => 999,
                ];
                $this->post($path, $input)->assertRedirect();
                $log     = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
                $summary = json_decode($log['submission'], true, flags: JSON_THROW_ON_ERROR);
                ksort($fields);
                ksort($summary['fields']);
                $this->assertSame($operation, $log['operation']);
                $this->assertSame($type, $log['target_type']);
                $this->assertSame($targetId, $log['target_id']);
                $this->assertSame($fields, $summary['fields']);
                $this->assertSame($result, $log['result']);
                $this->assertSame((int) $actor->id, (int) $log['actor_id']);
                $this->assertStringNotContainsString('private-secret', $log['submission']);
                $this->assertStringNotContainsString($target->email, $log['submission']);
                if ($operation === 'profile.token.create' && $result === 'redirected') {
                    $rawToken = session('alert')['detail'];
                    $this->assertNotEmpty($rawToken);
                    $this->assertStringNotContainsString($rawToken, json_encode($log));
                }
            }
            $this->post('/en/admin/settings/roles/audit-role/permissions', [csrf_token() => csrf_hash(), 'permissions' => ['users.view']])->assertRedirect();
            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertNull($log['operation']);
            $this->assertNull($log['submission']);
            $this->assertSame('audit-role', $log['target_id']);
            $this->assertSame('success', $log['result']);
            $before = db_connect()->table('operation_audit_logs')->countAllResults();
            $actor->removeGroup('superadmin');
            $this->post('/en/admin/settings/roles', [csrf_token() => csrf_hash(), 'name' => 'blocked-audit-role', 'title' => 'Blocked role'])->assertRedirect();
            $this->assertSame($before, db_connect()->table('operation_audit_logs')->countAllResults());
            $this->assertArrayNotHasKey('blocked-audit-role', setting('AuthGroups.groups'));
        } finally {
            service('settings')->setMany(array_filter($originalSettings, static fn ($value): bool => $value !== null));
            service('settings')->forgetMany(array_keys(array_filter($originalSettings, static fn ($value): bool => $value === null)));
            Services::resetSingle('mailtemplates');
            Services::resetSingle('settings');
        }
    }

    public function testJsonSubmissionUsesResolvedRouteAndDoesNotReadQuery(): void
    {
        $user        = new AdminUser(['username' => 'auditjson']);
        $user->email = 'auditjson@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));
        $routes = service('routes');
        $routes->loadRoutes();
        $routes->setHTTPVerb('POST');
        $routerRequest = $this->setupRequest('POST', '/en/admin/profile/language');
        $router        = Services::router($routes, $routerRequest, false);
        $router->handle('en/admin/profile/language');
        Services::injectMock('router', $router);
        $audit = new OperationAudit();

        foreach (['{"language":"en","password":"json-private"}' => ['language' => 'en'], '{invalid-private' => [], '["private"]' => []] as $body => $expected) {
            $request = $this->setupRequest('POST', '/en/admin/profile/language?language=query-private');
            $request->setHeader('Content-Type', $expected === [] ? 'application/problem+json' : 'application/json; charset=utf-8');
            $request->setBody($body);
            $audit->before($request);
            $request->setBody('{"language":"mutated"}');
            $audit->after($request, service('response')->setStatusCode(200));
            $log     = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $summary = json_decode($log['submission'], true);
            $this->assertSame('profile.language', $log['operation']);
            $this->assertSame($expected, $summary['fields']);
            if ($expected === []) {
                $this->assertSame('invalid_json', $summary['input']);
            }
            $this->assertStringNotContainsString('private', json_encode($log));
            $this->assertStringNotContainsString('mutated', $log['submission']);
        }
        $router->handle('en/admin/profile/password');
        $request = $this->setupRequest('POST', '/en/admin/profile/password');
        $audit->before($request);
        $audit->after($request, service('response')->setStatusCode(200));
        $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertNull($log['operation']);
        $this->assertNull($log['submission']);
    }

    public function testConfiguredEmailSubmissionAndPermissionRejection(): void
    {
        $user        = new AdminUser(['username' => 'auditemail']);
        $user->email = 'auditemail@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);
        $this->post('/en/admin/settings/email', [
            csrf_token() => csrf_hash(), 'protocol' => 'smtp', 'SMTPPort' => '587',
            'SMTPCrypto' => 'tls', 'SMTPPass' => 'private-secret',
        ])->assertRedirect();
        $log = db_connect()->table('operation_audit_logs')->get()->getRowArray();
        $this->assertSame('email.settings.update', $log['operation']);
        $this->assertSame('failed', $log['result']);
        $fields = json_decode($log['submission'], true)['fields'];
        ksort($fields);
        $this->assertSame(['SMTPCrypto' => 'tls', 'SMTPPort' => '587', 'protocol' => 'smtp'], $fields);
        $this->assertStringNotContainsString('private-secret', json_encode($log));
        $user->removeGroup('superadmin');
        $this->post('/en/admin/announcements/create', [csrf_token() => csrf_hash(), 'title' => 'Rejected', 'body' => 'Rejected'])->assertRedirect();
        $this->assertSame(1, db_connect()->table('operation_audit_logs')->countAllResults());
    }

    public function testNonPostFormSubmissionPreservesUnknownResult(): void
    {
        $user        = new AdminUser(['username' => 'auditforms']);
        $user->email = 'auditforms@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));
        $config               = config(Geminus\Admin\Config\OperationAudit::class);
        $original             = $config->operations;
        $config->operations[] = [
            'controller' => 'Geminus\\Admin\\Controllers\\Profile::language',
            'methods'    => ['PUT', 'PATCH', 'DELETE'], 'action' => 'profile.language',
            'object'     => ['type' => 'profile'], 'fields' => ['language'],
        ];

        try {
            foreach (['PUT', 'PATCH', 'DELETE'] as $httpMethod) {
                $request = $this->setupRequest($httpMethod, '/en/admin/audit-form');
                $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
                $request->setBody('language=zh-Hant&password=private-secret');
                $routes = service('routes');
                $routes->loadRoutes();
                $routes->{strtolower($httpMethod)}('en/admin/audit-form', '\\Geminus\\Admin\\Controllers\\Profile::language');
                $router = Services::router($routes, $request, false);
                $router->handle('en/admin/audit-form');
                Services::injectMock('router', $router);
                $audit = new OperationAudit();
                $audit->before($request);
                $audit->after($request, service('response')->setStatusCode(302));
                $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
                $this->assertSame('profile.language', $log['operation']);
                $this->assertSame(['language' => 'zh-Hant'], json_decode($log['submission'], true)['fields']);
                $this->assertSame('redirected', $log['result']);
                $this->assertStringNotContainsString('private-secret', json_encode($log));
            }
        } finally {
            $config->operations = $original;
        }
    }

    public function testSubmissionStatesAndMixedActionSortingAreSafe(): void
    {
        $user        = new AdminUser(['username' => 'auditstates']);
        $user->email = 'auditstates@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        foreach ([
            ['post.aaa', ['fields' => ['enabled' => true, 'optional' => null], 'omitted' => ['roles' => 'complex_value'], 'truncated' => ['title'], 'input' => 'invalid_json', 'limited' => true]],
            ['post.zzz', ['fields' => 'invalid-shape']],
            [null, null],
        ] as [$operation, $submission]) {
            db_connect()->table('operation_audit_logs')->insert([
                'action'      => 'POST', 'operation' => $operation, 'submission' => $submission === null ? null : json_encode($submission),
                'target_type' => 'profile', 'path' => $operation ?? 'legacy-marker',
                'result'      => 'success', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-10 00:00:00',
            ]);
        }
        $page = $this->get('/zh-Hans/admin/audit?sort=action&direction=ASC');
        $page->assertOK();
        $body = $page->response()->getBody();

        foreach (['roles: 已省略', 'title: 已截断', 'JSON 输入无效', '已达到采集上限', '>true<', '>null<'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
        $this->assertLessThan(strpos($body, 'post.aaa</div>'), strpos($body, 'legacy-marker</td>'));
        $this->assertLessThan(strpos($body, 'post.zzz</div>'), strpos($body, 'post.aaa</div>'));
    }

    public function testInvalidCaptureConfigurationDoesNotChangeBusinessResponse(): void
    {
        $user        = new AdminUser(['username' => 'auditcapturefailure']);
        $user->email = 'auditcapturefailure@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);
        $config             = config(Geminus\Admin\Config\OperationAudit::class);
        $original           = $config->operations;
        $config->operations = [['controller' => 'invalid-private-input']];

        try {
            $this->post('/en/admin/profile/language', [csrf_token() => csrf_hash(), 'language' => 'zh-Hant'])->assertRedirect();
            $this->assertSame('zh-Hant', $users->findById($user->id)->language);
            $log = db_connect()->table('operation_audit_logs')->get()->getRowArray();
            $this->assertNull($log['submission']);
            $this->assertNull($log['operation']);
            $this->assertSame('success', $log['result']);
            $this->assertLogged('error', 'Operation audit submission capture failed.');
        } finally {
            $config->operations = $original;
        }
    }

    public function testAuditMigrationCreatesNullableSubmissionFields(): void
    {
        $this->assertContains('operation', db_connect()->getFieldNames('operation_audit_logs'));
        $this->assertContains('submission', db_connect()->getFieldNames('operation_audit_logs'));
        db_connect()->table('operation_audit_logs')->insert([
            'action' => 'POST', 'target_type' => 'profile', 'path' => 'unconfigured-audit-record',
            'result' => 'redirected', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-10 12:00:00',
        ]);
        $log = db_connect()->table('operation_audit_logs')->get()->getRowArray();
        $this->assertSame('unconfigured-audit-record', $log['path']);
        $this->assertSame('redirected', $log['result']);
        $this->assertNull($log['operation']);
        $this->assertNull($log['submission']);
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
        $this->assertArrayHasKey('user_agent', $logs[0]);
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
        $this->assertSame(['profile.update', 'profile.update'], array_column($logs, 'operation'));
        $this->assertSame(['language' => 'en', 'timezone' => 'Asia/Shanghai'], json_decode($logs[1]['submission'], true)['fields']);
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

    public function testRepeatedFlashFeedbackClassifiesCurrentRedirect(): void
    {
        $user        = new AdminUser(['username' => 'auditrepeated']);
        $user->email = 'auditrepeated@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $request = $this->setupRequest('POST', '/en/admin/profile');
        $audit   = new OperationAudit();

        foreach ([
            ['alert', ['type' => 'success', 'message' => 'Saved'], 'success'],
            ['alert', ['type' => 'danger', 'message' => 'Rejected'], 'failed'],
            ['profile_errors', ['username' => 'Rejected'], 'failed'],
            ['_operation_audit_result', 'success', 'success'],
            ['_operation_audit_result', 'failed', 'failed'],
        ] as [$key, $feedback, $expected]) {
            session()->unmarkFlashdata(['alert', 'profile_errors', '_operation_audit_result']);
            session()->remove(['alert', 'profile_errors', '_operation_audit_result']);
            session()->setFlashdata($key, $feedback);
            $flashState       = session()->get('__ci_vars');
            $flashState[$key] = 'old';
            session()->set('__ci_vars', $flashState);

            $audit->before($request);
            $audit->after($request, service('response')->setStatusCode(302));
            $previous = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame('redirected', $previous['result']);

            session()->setFlashdata($key, $feedback);
            $audit->after($request, service('response')->setStatusCode(302));

            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertSame($expected, $log['result']);
        }
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
        session()->setFlashdata('_operation_audit_result', 'success');
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
                'action' => 'POST', 'target_type' => 'users', 'path' => 'en/admin/users', 'ip_address' => '127.0.0.1', 'user_agent' => 'AuditBrowser/1.0',
            ]);
        }

        $page = $this->get('/en/admin/audit?actor=' . $user->id . '&type=users&target=42&result=failed&from=2026-10-08&to=2026-10-08');
        $page->assertOK();
        TableLayoutAssertions::assertTablesInCards($page->response()->getBody(), paginated: true);
        $this->assertStringContainsString('users · ' . lang('Admin.auditDeletedUser') . ' #42</td>', $page->response()->getBody());
        $this->assertStringNotContainsString('users · ' . lang('Admin.auditDeletedUser') . ' #43</td>', $page->response()->getBody());
        $page->assertSee('auditviewer');
        $this->assertStringContainsString('value="' . $user->id . '" selected>auditviewer</option>', $page->response()->getBody());
        $page->assertSee('AuditBrowser/1.0');
        $this->assertStringContainsString('name="object"', $page->response()->getBody());
        $this->assertStringContainsString('<option value="">All actors</option>', $page->response()->getBody());
        $this->assertStringContainsString('<option value="">All objects</option>', $page->response()->getBody());
        $this->assertStringContainsString('data-bs-toggle="datepicker"', $page->response()->getBody());
        $this->assertStringContainsString('vanilla-calendar-pro/index.js', $page->response()->getBody());
        $this->assertStringContainsString('<select class="form-select" id="audit-actor" name="actor">', $page->response()->getBody());
        $this->assertStringContainsString('<select class="form-select" id="audit-target" name="object">', $page->response()->getBody());
        $this->assertStringNotContainsString('tom-select', $page->response()->getBody());
        $this->assertStringContainsString('new tabler.Datepicker', $page->response()->getBody());
        $this->assertStringContainsString('value="2026-10-08"', $page->response()->getBody());
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 1', $page->response()->getBody());
        $page->assertSee('Total: 1 (1 - 1)');
        $this->assertStringContainsString('private, no-store', $page->response()->getHeaderLine('Cache-Control'));

        $simplified = $this->get('/zh-Hans/admin/audit');
        $simplified->assertSee('全部操作者');
        $simplified->assertSee('全部对象');
        $traditional = $this->get('/zh-Hant/admin/audit');
        $traditional->assertSee('全部操作者');
        $traditional->assertSee('全部對象');

        $selected = $this->get('/en/admin/audit?object=users%7C42');
        $this->assertStringContainsString('users · ' . lang('Admin.auditDeletedUser') . ' #42</td>', $selected->response()->getBody());
        $this->assertStringNotContainsString('users · ' . lang('Admin.auditDeletedUser') . ' #43</td>', $selected->response()->getBody());
    }

    public function testAuditEmptyStatesReuseUiCells(): void
    {
        $user        = new AdminUser(['username' => 'auditempty']);
        $user->email = 'auditempty@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
            $empty = $this->get('/' . $locale . '/admin/audit');
            $empty->assertOK();
            TableLayoutAssertions::assertTablesInCards($empty->response()->getBody(), paginated: true);
            $empty->assertSee(lang('Admin.mailNoRecords'), 'h3');
            $this->assertStringNotContainsString('class="empty-action"', $empty->response()->getBody());
            $this->assertStringNotContainsString('(1 - 0)', $empty->response()->getBody());

            $filtered = $this->get('/' . $locale . '/admin/audit?object=roles%7Ceditor&from=2026-10-01&to=2026-10-09&result=failed&sort=path&direction=ASC');
            $filtered->assertOK();
            $document = new DOMDocument();
            $document->loadHTML($filtered->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $this->assertSame(1, $xpath->query('//div[@class="empty-action"]/a[@href="/' . $locale . '/admin/audit"]')->length);
            $this->assertSame('2026-10-01', $xpath->query('//input[@id="audit-from" and @data-bs-toggle="datepicker"]')->item(0)->getAttribute('value'));
            $this->assertSame('2026-10-09', $xpath->query('//input[@id="audit-to" and @data-bs-toggle="datepicker"]')->item(0)->getAttribute('value'));
            $this->assertSame('failed', $xpath->query('//select[@id="audit-result"]/option[@selected]')->item(0)->getAttribute('value'));
            $filtered->assertSee(lang('Admin.userClear'), 'a');

            $legacy = $this->get('/' . $locale . '/admin/audit?target=42');
            $legacy->assertOK();
            $document->loadHTML($legacy->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $this->assertSame(1, $xpath->query('//div[@class="empty-action"]/a[@href="/' . $locale . '/admin/audit"]')->length);
        }
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
            $request->setHeader('User-Agent', 'AuditBrowser/1.0');
            (new OperationAudit())->after($request, service('response')->setStatusCode($status));
            $log = db_connect()->table('operation_audit_logs')->where('path', $path)->get()->getRowArray();
            $this->assertSame($method, $log['action']);
            $this->assertSame($result, $log['result']);
            $this->assertSame($targetId, $log['target_id']);
            $this->assertSame($type, $log['target_type']);
            $this->assertSame('AuditBrowser/1.0', $log['user_agent']);
        }
    }

    public function testInvalidBrowserBytesDoNotDiscardAnAuditRecord(): void
    {
        $user        = new AdminUser(['username' => 'auditbrowser']);
        $user->email = 'auditbrowser@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $uri = $this->setupRequest('POST', '/en/admin/profile/language')->getUri();

        foreach (["Audit\x00Browser\xFF/1.0" => 'AuditBrowser?/1.0', str_repeat('A', 600) => str_repeat('A', 512)] as $agent => $expected) {
            $request = $this->createStub(RequestInterface::class);
            $request->method('getMethod')->willReturn('POST');
            $request->method('getUri')->willReturn($uri);
            $request->method('getIPAddress')->willReturn('127.0.0.1');
            $request->method('getHeaderLine')->willReturn($agent);
            (new OperationAudit())->after($request, service('response'));

            $log = db_connect()->table('operation_audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
            $this->assertNotNull($log);
            $this->assertSame($expected, $log['user_agent']);
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
        $first->assertSee('Total: 21 (1 - 20)');
        $last = $this->get('/en/admin/audit?page=999');
        $last->assertSee('audit-record-1');
        $last->assertDontSee('audit-record-21</td>');
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 21', $last->response()->getBody());
        $last->assertSee('Total: 21 (21 - 21)');

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
        $document = new DOMDocument();
        $document->loadHTML($filtered->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath    = new DOMXPath($document);
        $nextPage = null;

        foreach ($xpath->query('//ul[contains(@class, "pagination")]//a[@href]') as $link) {
            $href = $link->getAttribute('href');
            parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
            $this->assertSame('failed', $query['result'] ?? null);
            if (($query['page'] ?? null) === '2') {
                $nextPage = parse_url($href, PHP_URL_PATH) . '?' . parse_url($href, PHP_URL_QUERY);
            }
        }
        $this->assertNotNull($nextPage);
        $second = $this->get($nextPage);
        $second->assertSee('audit-record-1</td>');
        $second->assertDontSee('audit-record-21</td>');
        $second->assertSee('Total: 21 (21 - 21)');
        $second->assertDontSee('audit-success-marker');
    }

    public function testAuditHeadersSortAllColumnsAndPreserveFilters(): void
    {
        $users = auth()->getProvider();

        foreach (['z', 'a', 'm', 'b'] as $name) {
            $username    = 'sort-' . $name;
            $user        = new AdminUser(['username' => $username]);
            $user->email = $username . '@example.com';
            $user->setPassword('A-local-password-123!');
            $users->save($user);
            $actors[$name] = $users->findById($users->getInsertID());
        }
        $actors['z']->addGroup('superadmin');
        auth()->login($actors['z']);

        foreach ([
            ['z', '2026-10-03 12:00:00', 'GET', 'users', '9', 'c', 'success', '127.0.0.4', 'SortBrowser/D'],
            ['a', '2026-10-01 12:00:00', 'PUT', 'roles', '1', 'd', 'failed', '127.0.0.3', 'SortBrowser/B'],
            ['m', '2026-10-04 12:00:00', 'POST', 'profile', '5', 'a', 'redirected', '127.0.0.2', 'SortBrowser/C'],
            ['b', '2026-10-02 12:00:00', 'DELETE', 'tokens', '2', 'b', 'success', '127.0.0.1', 'SortBrowser/A'],
        ] as [$actor, $createdAt, $action, $type, $target, $pathRank, $result, $ip, $agent]) {
            db_connect()->table('operation_audit_logs')->insert([
                'actor_id'    => $actors[$actor]->id, 'created_at' => $createdAt, 'action' => $action,
                'target_type' => $type, 'target_id' => $target, 'path' => $pathRank . '-sort-path-' . $actor,
                'result'      => $result, 'ip_address' => $ip, 'user_agent' => $agent,
            ]);
        }

        foreach ([
            'created_at' => ['a', 'b', 'z', 'm'], 'actor' => ['a', 'b', 'm', 'z'],
            'action'     => ['b', 'z', 'm', 'a'], 'object' => ['m', 'a', 'b', 'z'],
            'path'       => ['m', 'b', 'z', 'a'], 'result' => ['a', 'm', 'b', 'z'],
            'ip_address' => ['b', 'm', 'a', 'z'], 'user_agent' => ['b', 'a', 'm', 'z'],
        ] as $sort => $ascending) {
            foreach (['ASC', 'DESC'] as $direction) {
                $page = $this->get('/en/admin/audit?sort=' . $sort . '&direction=' . $direction);
                $page->assertOK();
                $body = $page->response()->getBody();
                $this->assertStringContainsString('name="sort" value="' . $sort . '" class="table-sort ' . strtolower($direction) . '"', $body);
                $this->assertSame(1, substr_count($body, 'aria-sort="' . ($direction === 'ASC' ? 'ascending' : 'descending') . '"'));
                $this->assertSame(7, substr_count($body, 'aria-sort="none"'));
                preg_match_all('/[a-d]-sort-path-([azmb])<\/td>/', $body, $matches);
                $expected = $direction === 'ASC' ? $ascending : array_reverse($ascending);
                if ($sort === 'result' && $direction === 'DESC') {
                    $expected = ['b', 'z', 'm', 'a'];
                }
                $this->assertSame($expected, $matches[1], $sort . ' ' . $direction);
            }
        }

        $filtered = $this->get('/en/admin/audit?actor=' . $actors['a']->id . '&object=roles%7C1&from=2026-10-01&to=2026-10-01&result=failed&sort=actor&direction=ASC');
        $filtered->assertSee('d-sort-path-a');
        $filtered->assertDontSee('c-sort-path-z');
        $this->assertStringContainsString('name="actor" value="' . $actors['a']->id . '"', $filtered->response()->getBody());

        foreach (['from' => '2026-10-01', 'to' => '2026-10-01', 'result' => 'failed'] as $name => $value) {
            $this->assertStringContainsString('name="' . $name . '" value="' . $value . '"', $filtered->response()->getBody());
        }
        $this->assertStringContainsString('name="direction" value="DESC"', $filtered->response()->getBody());
        $this->assertStringContainsString('name="sort" value="actor"', $filtered->response()->getBody());
        $document = new DOMDocument();
        $document->loadHTML($filtered->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//thead/tr/th/form') as $form) {
            foreach (['actor' => (string) $actors['a']->id, 'object' => 'roles|1', 'from' => '2026-10-01', 'to' => '2026-10-01', 'result' => 'failed'] as $name => $value) {
                $this->assertSame($value, $xpath->query('input[@name="' . $name . '"]', $form)->item(0)->getAttribute('value'));
            }
            $sortField = $xpath->query('button[@name="sort"]', $form)->item(0)->getAttribute('value');
            $this->assertSame($sortField === 'actor' ? 'DESC' : 'ASC', $xpath->query('input[@name="direction"]', $form)->item(0)->getAttribute('value'));
            $this->assertSame(0, $xpath->query('input[@name="page"]', $form)->length);
        }
        $this->assertSame(8, $xpath->query('//thead/tr/th/form')->length);

        $default = $this->get('/en/admin/audit?sort=invalid&direction=invalid');
        preg_match_all('/[a-d]-sort-path-([azmb])<\/td>/', $default->response()->getBody(), $matches);
        $this->assertSame(['m', 'z', 'b', 'a'], $matches[1]);
        $this->assertStringContainsString('name="sort" value="created_at" class="table-sort desc"', $default->response()->getBody());

        $invalidDirection = $this->get('/en/admin/audit?sort=actor&direction=invalid');
        preg_match_all('/[a-d]-sort-path-([azmb])<\/td>/', $invalidDirection->response()->getBody(), $matches);
        $this->assertSame(['z', 'm', 'b', 'a'], $matches[1]);
    }

    public function testAuditObjectSortingUsesVisibleUserNames(): void
    {
        $users = auth()->getProvider();

        foreach (['zeta', 'alpha'] as $name) {
            $user        = new AdminUser(['username' => 'audit-' . $name]);
            $user->email = 'audit-' . $name . '@example.com';
            $user->setPassword('A-local-password-123!');
            $users->save($user);
            $targets[$name] = $users->findById($users->getInsertID());

            db_connect()->table('operation_audit_logs')->insert([
                'actor_id'  => $targets[$name]->id, 'action' => 'POST', 'target_type' => 'users',
                'target_id' => (string) $targets[$name]->id, 'path' => 'object-' . $name,
                'result'    => 'success', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-08 12:00:00',
            ]);
        }
        $targets['zeta']->addGroup('superadmin');
        auth()->login($targets['zeta']);

        foreach (['ASC' => ['alpha', 'zeta'], 'DESC' => ['zeta', 'alpha']] as $direction => [$first, $second]) {
            $page = $this->get('/en/admin/audit?sort=object&direction=' . $direction);
            $page->assertOK();
            $body = $page->response()->getBody();
            $this->assertLessThan(strpos($body, 'object-' . $second . '</td>'), strpos($body, 'object-' . $first . '</td>'));
        }
    }

    public function testAuditSortingAppliesBeforePaginationAndKeepsQueryParameters(): void
    {
        $user        = new AdminUser(['username' => 'sort-pages']);
        $user->email = 'sort-pages@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        for ($number = 1; $number <= 21; $number++) {
            db_connect()->table('operation_audit_logs')->insert([
                'actor_id'  => $user->id, 'action' => 'POST', 'target_type' => 'roles',
                'target_id' => (string) $number, 'path' => sprintf('sort-page-%02d', $number),
                'result'    => 'failed', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-08 12:00:00',
            ]);
        }

        $first = $this->get('/en/admin/audit?result=failed&sort=path&direction=ASC');
        $first->assertSee('sort-page-01');
        $first->assertDontSee('sort-page-21</td>');
        $document = new DOMDocument();
        $document->loadHTML($first->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath    = new DOMXPath($document);
        $nextPage = null;

        foreach ($xpath->query('//ul[contains(@class, "pagination")]//a[@href]') as $link) {
            $href = $link->getAttribute('href');
            parse_str((string) parse_url($href, PHP_URL_QUERY), $query);

            foreach (['result' => 'failed', 'sort' => 'path', 'direction' => 'ASC'] as $name => $value) {
                $this->assertSame($value, $query[$name] ?? null);
            }
            if (($query['page'] ?? null) === '2') {
                $nextPage = parse_url($href, PHP_URL_PATH) . '?' . parse_url($href, PHP_URL_QUERY);
            }
        }
        $this->assertNotNull($nextPage);
        $second = $this->get($nextPage);
        $second->assertSee('sort-page-21');
        $second->assertDontSee('sort-page-01</td>');
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
        $page->assertSee('roles · editor');
        $this->assertStringNotContainsString('roles · reader</td>', $page->response()->getBody());
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 1', $page->response()->getBody());
    }

    public function testObjectDropdownFiltersTypeAndDisplaysExistingUserName(): void
    {
        $user        = new AdminUser(['username' => 'auditobject']);
        $user->email = 'auditobject@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('superadmin');
        auth()->login($user);

        foreach ([['roles', 'editor'], ['roles', 'reader'], ['profile', null], ['users', (string) $user->id], ['users', '99999999999']] as [$type, $target]) {
            db_connect()->table('operation_audit_logs')->insert([
                'actor_id'  => $user->id, 'action' => 'POST', 'target_type' => $type,
                'target_id' => $target, 'path' => 'audit-' . $type . '-' . $target,
                'result'    => 'success', 'ip_address' => '127.0.0.1', 'created_at' => '2026-10-08 12:00:00',
            ]);
        }

        $roles = $this->get('/en/admin/audit?object=roles%7C');
        $roles->assertOK();
        $this->assertStringContainsString('value="roles|" selected', $roles->response()->getBody());
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 2', $roles->response()->getBody());

        $profile = $this->get('/en/admin/audit?object=profile%7C');
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 1', $profile->response()->getBody());
        $this->assertStringNotContainsString('profile · </td>', $profile->response()->getBody());

        $selected = $this->get('/en/admin/audit?object=users%7C' . $user->id);
        $selected->assertOK();
        $document = new DOMDocument();
        $document->loadHTML($selected->response()->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertSame('users|' . $user->id, $xpath->query('//select[@id="audit-target"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertStringContainsString('users · auditobject</td>', $selected->response()->getBody());
        $this->assertStringContainsString(lang('Admin.userTotal') . ': 1', $selected->response()->getBody());
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
            'result'    => 'failed', 'ip_address' => '127.0.0.1', 'user_agent' => '<img src=x onerror=alert(1)>', 'created_at' => '2026-10-08 12:00:00',
        ]);

        $body = $this->get('/en/admin/audit')->response()->getBody();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $body);
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
