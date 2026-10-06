<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Config\MicrosoftOAuth;
use Geminus\Admin\Entities\AdminUser;

/**
 * @internal
 */
final class MicrosoftSettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testConfigResolvesFromAdminModule(): void
    {
        $this->assertInstanceOf(MicrosoftOAuth::class, config('MicrosoftOAuth'));
        $this->assertSame('', service('settings')->get('MicrosoftOAuth.clientId'));
    }

    public function testOnlyAuthorizedUsersCanAccessSettings(): void
    {
        $this->get('/en/admin/settings/microsoft')->assertRedirect();
        $this->post('/en/admin/settings/microsoft', $this->validSettings())->assertRedirect();

        $this->loginAs('admin');
        $this->get('/en/admin/settings/microsoft')->assertRedirect();
        $this->post('/en/admin/settings/microsoft', $this->validSettings())->assertRedirect();
        $this->assertSame('', service('settings')->get('MicrosoftOAuth.clientId'));
    }

    public function testSettingsPostRequiresCsrf(): void
    {
        $this->loginAs('superadmin');
        $this->expectException(SecurityException::class);

        $this->post('/en/admin/settings/microsoft', ['tenant' => 'invalid']);
    }

    public function testSettingsCanBeSavedAndDisplayedWithoutExposingSecret(): void
    {
        $this->loginAs('superadmin');
        $page = $this->get('/en/admin/settings/microsoft');
        $page->assertOK();
        $page->assertSee('Microsoft Entra app registration');
        $this->assertStringContainsString('name="' . csrf_token() . '"', $page->response()->getBody());
        $this->assertStringNotContainsString('name="clientSecret"', $page->response()->getBody());

        $this->post('/en/admin/settings/microsoft', $this->validSettings())->assertRedirect();
        $this->assertSame('11111111-2222-3333-4444-555555555555', service('settings')->get('MicrosoftOAuth.tenant'));
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', service('settings')->get('MicrosoftOAuth.clientId'));
        $this->assertSame('', service('settings')->get('MicrosoftOAuth.clientSecret'));
        $this->assertSame(lang('Admin.microsoftSettingsSaved'), session('alert')['message']);
        $this->assertStringContainsString('value="aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee"', $this->get('/en/admin/settings/microsoft')->response()->getBody());
        $this->get('/zh-Hans/admin/settings/microsoft')->assertSee('微软登录');
        $this->get('/zh-Hant/admin/settings/microsoft')->assertSee('微軟登入');
    }

    public function testInvalidIdentifiersDoNotOverwriteSavedConfiguration(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/settings/microsoft', $this->validSettings())->assertRedirect();

        $invalid             = $this->validSettings();
        $invalid['tenant']   = 'common';
        $invalid['clientId'] = 'not-a-guid';
        $this->post('/en/admin/settings/microsoft', $invalid)->assertRedirect();

        $this->assertNotEmpty(session('microsoft_errors.tenant'));
        $this->assertNotEmpty(session('microsoft_errors.clientId'));
        $this->assertSame('11111111-2222-3333-4444-555555555555', service('settings')->get('MicrosoftOAuth.tenant'));
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', service('settings')->get('MicrosoftOAuth.clientId'));
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'microsoftsettings' . $group]);
        $user->email = 'microsoftsettings@example.com';
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
            'tenant'     => '11111111-2222-3333-4444-555555555555',
            'clientId'   => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ];
    }
}
