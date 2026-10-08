<?php

use CodeIgniter\Email\Email;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Geminus\Admin\Entities\AdminUser;

/**
 * @internal
 */
final class EmailSettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        Services::resetSingle('email');
        auth()->logout();
        parent::tearDown();
    }

    public function testAnonymousUserCannotOpenEmailSettings(): void
    {
        $this->get('/en/admin/settings/email')->assertRedirect();
        $this->get('/en/admin/settings/email/queue')->assertRedirect();
        $this->post('/en/admin/settings/email', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirect();
    }

    public function testUserWithoutSettingsPermissionCannotReadOrSave(): void
    {
        $this->loginAs('admin');

        $this->get('/en/admin/settings/email')->assertRedirect();
        $this->get('/en/admin/settings/email/queue?view=queue')->assertRedirect();
        $this->post('/en/admin/settings/email', $this->validSettings())->assertRedirect();
        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirect();
        $this->assertSame('', service('settings')->get('Email.fromEmail'));
    }

    public function testEmailSettingsPostRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/email', ['fromEmail' => 'sender@example.com']);
    }

    public function testEmailQueuePageFiltersLogsAndNeverRendersQueuePayload(): void
    {
        $this->loginAs('superadmin');
        $db = db_connect();
        $db->table('queue_jobs')->insert([
            'queue'    => 'email', 'payload' => 'Private body and smtp-secret', 'status' => 0,
            'attempts' => 1, 'created_at' => time(), 'available_at' => time(),
        ]);
        $jobId = $db->insertID();
        $db->table('email_delivery_logs')->insert([
            'job_id'         => $jobId, 'recipient' => 'target@example.com', 'subject' => '<Private subject>',
            'status'         => 'failed', 'attempts' => 1, 'created_at' => date('Y-m-d H:i:s'),
            'failure_reason' => 'SMTP server rejected message (code 550).',
        ]);
        $db->table('email_delivery_logs')->insert([
            'recipient' => 'other@example.com', 'subject' => 'Other', 'status' => 'sent',
            'attempts'  => 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('queue_jobs')->insert([
            'queue'    => 'other', 'payload' => 'Other queue secret', 'status' => 0,
            'attempts' => 0, 'created_at' => time(), 'available_at' => time(),
        ]);

        $logs = $this->get('/en/admin/settings/email/queue?status=failed&recipient=target');
        $logs->assertOK();
        $logs->assertSee('SMTP server rejected message (code 550).');
        $this->assertStringContainsString('&lt;Private subject&gt;', $logs->response()->getBody());
        $this->assertStringNotContainsString('other@example.com', $logs->response()->getBody());
        $this->assertStringNotContainsString('Private body', $logs->response()->getBody());
        $this->assertStringContainsString('private, no-store', $logs->response()->getHeaderLine('Cache-Control'));

        $queue = $this->get('/en/admin/settings/email/queue?view=queue');
        $queue->assertOK();
        $queue->assertSee('target@example.com');
        $this->assertStringNotContainsString('Private body', $queue->response()->getBody());
        $this->assertStringNotContainsString('smtp-secret', $queue->response()->getBody());
        $this->assertStringContainsString('Total: 1', $queue->response()->getBody());
        $this->get('/zh-Hans/admin/settings/email/queue')->assertSee('邮件队列与发送审计');
    }

    public function testEmailQueueAndAuditTimesUseViewerTimezone(): void
    {
        $this->loginAs('superadmin');
        $viewer           = auth()->user();
        $viewer->timezone = 'Asia/Shanghai';
        auth()->getProvider()->save($viewer);

        $db = db_connect();
        $db->table('queue_jobs')->insert([
            'queue'        => 'email', 'payload' => 'Private body', 'status' => 0, 'attempts' => 0,
            'created_at'   => strtotime('2026-01-01 00:00:00 UTC'),
            'available_at' => strtotime('2026-01-01 01:15:00 UTC'),
        ]);
        $db->table('email_delivery_logs')->insert([
            'job_id'       => $db->insertID(), 'recipient' => 'time@example.com', 'subject' => 'Timezone check',
            'status'       => 'sent', 'attempts' => 1, 'created_at' => '2026-01-01 00:00:00',
            'processed_at' => '2026-01-01 01:15:00',
        ]);

        $logs = $this->get('/en/admin/settings/email/queue');
        $logs->assertOK();
        $this->assertStringContainsString('<td class="text-nowrap">2026-01-01 08:00:00</td>', $logs->response()->getBody());
        $this->assertStringContainsString('<td class="text-nowrap">2026-01-01 09:15:00</td>', $logs->response()->getBody());

        $queue = $this->get('/en/admin/settings/email/queue?view=queue');
        $queue->assertOK();
        $this->assertStringContainsString('<td class="text-nowrap">2026-01-01 08:00:00</td>', $queue->response()->getBody());
        $this->assertStringContainsString('<td class="text-nowrap">2026-01-01 09:15:00</td>', $queue->response()->getBody());
    }

    public function testEmailAuditPaginationRetainsFilters(): void
    {
        $this->loginAs('superadmin');

        for ($index = 0; $index < 21; $index++) {
            db_connect()->table('email_delivery_logs')->insert([
                'recipient' => 'paging@example.com', 'subject' => 'Page ' . $index,
                'status'    => 'failed', 'attempts' => 1, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $first = $this->get('/en/admin/settings/email/queue?status=failed&recipient=paging');
        $first->assertOK();
        $this->assertStringContainsString('page=2', $first->response()->getBody());
        $this->assertStringContainsString('status=failed', $first->response()->getBody());
        $this->assertStringContainsString('recipient=paging', $first->response()->getBody());

        $second = $this->get('/en/admin/settings/email/queue?status=failed&recipient=paging&page=2');
        $second->assertOK();
        $this->assertStringContainsString('Page 0', $second->response()->getBody());
        $this->assertStringNotContainsString('Page 20', $second->response()->getBody());
    }

    public function testSettingsAreSavedAndUsedByEmailService(): void
    {
        $this->loginAs('superadmin');

        $page = $this->get('/en/admin/settings/email');
        $page->assertOK();
        $page->assertSee('Email delivery');
        $this->assertStringContainsString('name="' . csrf_token() . '"', $page->response()->getBody());
        $this->assertStringNotContainsString('name="SMTPPass"', $page->response()->getBody());
        $this->assertStringContainsString('class="form-label required" for="email-fromEmail"', $page->response()->getBody());
        $this->assertStringContainsString('class="form-label required" for="email-SMTPHost"', $page->response()->getBody());
        $this->assertStringContainsString('class="form-label" for="email-SMTPUser"', $page->response()->getBody());
        $this->assertStringContainsString('action="/en/admin/settings/email/test"', $page->response()->getBody());
        $this->assertStringContainsString('name="test_email" type="email"', $page->response()->getBody());
        $this->assertStringContainsString('src="/static/js/form-submission.js"', $page->response()->getBody());
        $this->assertStringContainsString('class="d-none" data-submit-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Sending test email', $page->response()->getBody());

        $this->post('/en/admin/settings/email', $this->validSettings())->assertRedirect();
        $this->assertSame('sender@example.com', service('settings')->get('Email.fromEmail'));
        $this->assertSame(587, service('settings')->get('Email.SMTPPort'));

        $email = service('email', null, false);
        $this->assertSame('smtp', $email->protocol);
        $this->assertSame('smtp.example.com', $email->SMTPHost);
        $this->assertSame('sender@example.com', $email->fromEmail);
        $this->assertSame(config('Email')->SMTPPass, $email->SMTPPass);
        $this->get('/zh-Hans/admin/settings/email')->assertSee('邮件发送');
        $this->get('/zh-Hant/admin/settings/email')->assertSee('郵件發送');
        $this->assertStringContainsString('data-submit-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>正在发送测试邮件', $this->get('/zh-Hans/admin/settings/email')->response()->getBody());
    }

    public function testInvalidSettingsDoNotOverwriteSavedConfiguration(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/settings/email', $this->validSettings())->assertRedirect();

        $invalid              = $this->validSettings();
        $invalid['fromEmail'] = 'invalid';
        $invalid['SMTPPort']  = '70000';
        $this->post('/en/admin/settings/email', $invalid)->assertRedirect();
        $this->assertSame('sender@example.com', service('settings')->get('Email.fromEmail'));
        $this->assertSame(587, service('settings')->get('Email.SMTPPort'));

        $invalid             = $this->validSettings();
        $invalid['SMTPHost'] = '';
        $this->post('/en/admin/settings/email', $invalid)->assertRedirect();
        $this->assertSame('smtp.example.com', service('settings')->get('Email.SMTPHost'));
    }

    public function testMailProtocolDoesNotRequireOrEraseSmtpSettings(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('Email.protocol', 'mail');
        $page = $this->get('/en/admin/settings/email');
        $this->assertStringContainsString('id="email-smtp-settings" class="d-none"', $page->response()->getBody());

        $this->post('/en/admin/settings/email', $this->validSettings())->assertRedirect();
        $page = $this->get('/en/admin/settings/email');
        $this->assertStringContainsString('id="email-smtp-settings">', $page->response()->getBody());

        $this->post('/en/admin/settings/email', [
            csrf_token() => csrf_hash(),
            'fromEmail'  => 'new@example.com',
            'fromName'   => 'Example',
            'protocol'   => 'mail',
        ])->assertRedirect();

        $this->assertSame('mail', service('settings')->get('Email.protocol'));
        $this->assertSame('new@example.com', service('settings')->get('Email.fromEmail'));
        $this->assertSame('smtp.example.com', service('settings')->get('Email.SMTPHost'));
        $this->assertSame(587, service('settings')->get('Email.SMTPPort'));
    }

    public function testSendingTestEmailRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/email/test', ['test_email' => 'recipient@example.com']);
    }

    public function testInvalidRecipientAndMissingSenderDoNotSend(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('Email.fromEmail', '');
        $email = $this->createMock(Email::class);
        $email->expects($this->never())->method('send');
        Services::injectMock('email', $email);

        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'invalid'])->assertRedirect();
        $this->assertNotEmpty(session('test_email_errors.test_email'));

        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirect();
        $this->assertSame('danger', session('alert')['type']);
        $this->assertSame(lang('Admin.testEmailNotConfigured'), session('alert')['message']);
    }

    public function testSendingTestEmailReportsSuccessAndFailure(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('Email.fromEmail', 'sender@example.com');

        foreach ([true, false] as $sent) {
            $email           = $this->createMock(Email::class);
            $email->SMTPPass = 'test-secret';
            $email->SMTPUser = 'test-user';
            $email->expects($this->once())->method('setTo')->with('recipient@example.com');
            $email->expects($this->once())->method('setSubject')->with(lang('Admin.testEmailSubject'));
            $email->expects($this->once())->method('setMessage')->with(lang('Admin.testEmailBody'));
            $email->expects($this->once())->method('send')->willReturn($sent);
            $email->expects($sent ? $this->never() : $this->once())->method('printDebugger')->with([])
                ->willReturn('<pre>SMTP 535 authentication failed for test-user: test-secret</pre>');
            Services::injectMock('email', $email);

            $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirect();
            $this->assertSame($sent ? 'success' : 'danger', session('alert')['type']);
            $this->assertSame(lang($sent ? 'Admin.testEmailSent' : 'Admin.testEmailFailed'), session('alert')['message']);
            if ($sent) {
                $this->assertArrayNotHasKey('detail', session('alert'));
            } else {
                $this->assertStringContainsString('SMTP 535 authentication failed', session('alert')['detail']);
                $this->assertStringNotContainsString('test-secret', session('alert')['detail']);
                $this->assertStringNotContainsString('test-user', session('alert')['detail']);
                $this->assertStringNotContainsString('<pre>', session('alert')['detail']);
            }
        }
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'mailsettings' . $group]);
        $user->email = 'mailsettings@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }

    private function validSettings(): array
    {
        return [
            csrf_token() => csrf_hash(),
            'fromEmail'  => 'sender@example.com',
            'fromName'   => 'Example',
            'protocol'   => 'smtp',
            'SMTPHost'   => 'smtp.example.com',
            'SMTPUser'   => 'sender',
            'SMTPPort'   => '587',
            'SMTPCrypto' => 'tls',
        ];
    }
}
