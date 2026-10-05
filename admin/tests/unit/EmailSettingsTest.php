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
        $this->post('/en/admin/settings/email', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->post('/en/admin/settings/email/test', [csrf_token() => csrf_hash(), 'test_email' => 'recipient@example.com'])->assertRedirect();
    }

    public function testUserWithoutSettingsPermissionCannotReadOrSave(): void
    {
        $this->loginAs('admin');

        $this->get('/en/admin/settings/email')->assertRedirect();
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
