<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Geminus\Admin\Config\MicrosoftOAuth;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\MicrosoftLinks;
use Geminus\Admin\Libraries\OrganizationAzure;

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
        $this->assertSame('organizations', service('settings')->get('MicrosoftOAuth.tenant'));
        $this->assertSame('2.0', MicrosoftOAuth::ENDPOINT_VERSION);
        $this->assertFalse(service('settings')->get('MicrosoftOAuth.enabled'));
        $this->assertSame('', service('settings')->get('MicrosoftOAuth.clientId'));
    }

    public function testOrganizationProviderValidatesTenantSpecificIssuer(): void
    {
        $tenant   = '11111111-2222-3333-4444-555555555555';
        $provider = new class (['clientId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'tenant' => 'organizations', 'defaultEndPointVersion' => MicrosoftOAuth::ENDPOINT_VERSION]) extends OrganizationAzure {
            public function getTenantDetails($tenant, $version)
            {
                return ['issuer' => 'https://login.microsoftonline.com/' . $tenant . '/v2.0'];
            }
        };

        $provider->validateTokenClaims([
            'aud' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'tid' => $tenant,
            'oid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
            'ver' => '2.0',
            'iss' => 'https://login.microsoftonline.com/' . $tenant . '/v2.0',
            'nbf' => time() - 60,
            'exp' => time() + 60,
        ]);

        $this->assertSame($tenant, $provider->tenant);
    }

    public function testOrganizationProviderRejectsPersonalTenant(): void
    {
        $provider = new OrganizationAzure(['clientId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'tenant' => 'organizations']);
        $this->expectException(RuntimeException::class);
        $provider->validateTokenClaims([
            'tid' => '9188040d-6c67-4c5b-b112-36a304b66dad',
            'oid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
            'ver' => '2.0',
        ]);
    }

    public function testOrganizationProviderRejectsLegacyVersion(): void
    {
        $provider = new OrganizationAzure(['clientId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'tenant' => 'organizations']);
        $this->expectException(RuntimeException::class);
        $provider->validateTokenClaims([
            'tid' => '11111111-2222-3333-4444-555555555555',
            'oid' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
            'ver' => '1.0',
        ]);
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
        $this->assertStringContainsString('name="enabled" value="1"', $page->response()->getBody());
        $this->assertStringNotContainsString('name="clientSecret"', $page->response()->getBody());

        $this->post('/en/admin/settings/microsoft', $this->validSettings())->assertRedirect();
        $this->assertSame('11111111-2222-3333-4444-555555555555', service('settings')->get('MicrosoftOAuth.tenant'));
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', service('settings')->get('MicrosoftOAuth.clientId'));
        $this->assertFalse(service('settings')->get('MicrosoftOAuth.enabled'));
        $this->assertSame(0, Database::connect()->table('settings')->where('class', MicrosoftOAuth::class)->where('key', 'clientSecret')->countAllResults());
        $this->assertSame(lang('Admin.microsoftSettingsSaved'), session('alert')['message']);
        $this->assertStringContainsString('value="aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee"', $this->get('/en/admin/settings/microsoft')->response()->getBody());
        $this->get('/zh-Hans/admin/settings/microsoft')->assertSee('微软登录');
        $this->get('/zh-Hant/admin/settings/microsoft')->assertSee('微軟登入');
    }

    public function testOrganizationsTenantAllowsMultipleOrganizationsButRejectsPersonalAccounts(): void
    {
        $this->loginAs('superadmin');
        $settings           = $this->validSettings();
        $settings['tenant'] = 'organizations';
        $this->post('/en/admin/settings/microsoft', $settings)->assertRedirect();

        $this->assertSame('organizations', service('settings')->get('MicrosoftOAuth.tenant'));
        $this->assertStringContainsString('value="organizations"', $this->get('/en/admin/settings/microsoft')->response()->getBody());

        foreach (['common', 'consumers'] as $tenant) {
            $settings           = $this->validSettings();
            $settings['tenant'] = $tenant;
            $this->post('/en/admin/settings/microsoft', $settings)->assertRedirect();

            $this->assertNotEmpty(session('microsoft_errors.tenant'));
            $this->assertSame('organizations', service('settings')->get('MicrosoftOAuth.tenant'));
        }
    }

    public function testSwitchCanBeEnabledAndDisabled(): void
    {
        $this->loginAs('superadmin');
        $enabled            = $this->validSettings();
        $enabled['enabled'] = '1';
        $this->post('/en/admin/settings/microsoft', $enabled)->assertRedirect();

        $this->assertTrue(service('settings')->get('MicrosoftOAuth.enabled'));
        $this->assertStringContainsString('name="enabled" value="1" checked', $this->get('/en/admin/settings/microsoft')->response()->getBody());

        $this->post('/en/admin/settings/microsoft', $this->validSettings())->assertRedirect();
        $this->assertFalse(service('settings')->get('MicrosoftOAuth.enabled'));
        $this->assertStringNotContainsString('name="enabled" value="1" checked', $this->get('/en/admin/settings/microsoft')->response()->getBody());
    }

    public function testInvalidSwitchValueDoesNotOverwriteSettings(): void
    {
        $this->loginAs('superadmin');
        $enabled            = $this->validSettings();
        $enabled['enabled'] = '1';
        $this->post('/en/admin/settings/microsoft', $enabled)->assertRedirect();
        $originalTenant     = service('settings')->get('MicrosoftOAuth.tenant');
        $invalid            = $this->validSettings();
        $invalid['enabled'] = 'invalid';
        $this->post('/en/admin/settings/microsoft', $invalid)->assertRedirect();

        $this->assertNotEmpty(session('microsoft_errors.enabled'));
        $this->assertTrue(service('settings')->get('MicrosoftOAuth.enabled'));
        $this->assertSame($originalTenant, service('settings')->get('MicrosoftOAuth.tenant'));
    }

    public function testUncheckedSwitchRemainsUncheckedWhenValidationFails(): void
    {
        $this->loginAs('superadmin');
        $enabled            = $this->validSettings();
        $enabled['enabled'] = '1';
        $this->post('/en/admin/settings/microsoft', $enabled)->assertRedirect();

        $invalid            = $this->validSettings();
        $invalid['enabled'] = '0';
        $invalid['tenant']  = 'invalid';
        $this->post('/en/admin/settings/microsoft', $invalid)->assertRedirect();

        $this->assertTrue(service('settings')->get('MicrosoftOAuth.enabled'));
        $_SESSION ??= [];
        session()->set('_ci_old_input', ['post' => $invalid, 'get' => []]);
        $page = view('Geminus\Admin\Views\settings_microsoft', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.microsoftLogin'),
            'microsoft'  => service('settings')->getMany(['MicrosoftOAuth.enabled', 'MicrosoftOAuth.tenant', 'MicrosoftOAuth.clientId']),
        ]);
        $this->assertStringNotContainsString('name="enabled" value="1" checked', $page);
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

    public function testMicrosoftLoginIsUnavailableWhenDisabledAndRejectsInvalidCallback(): void
    {
        $this->get('/en/login')->assertOK();
        $this->assertStringNotContainsString('microsoft/start', $this->get('/en/login')->response()->getBody());
        $this->get('/en/microsoft/start')->assertRedirect();
        $this->get('/en/microsoft/callback?code=invalid&state=invalid')->assertRedirect();
        $this->assertFalse(auth()->loggedIn());
    }

    public function testMicrosoftCallbackRejectsMismatchedOrExpiredState(): void
    {
        service('settings')->set('MicrosoftOAuth.enabled', true);
        $this->withSession(['microsoft_flow' => ['state' => 'expected', 'mode' => 'login', 'expires' => time() + 60]])
            ->get('/en/microsoft/callback?code=fake&state=other')->assertRedirect();
        $this->assertNull(session('microsoft_flow'));

        $this->withSession(['microsoft_flow' => ['state' => 'expected', 'mode' => 'login', 'locale' => 'zh-Hant', 'expires' => time() - 1]])
            ->get('/en/microsoft/callback?code=fake&state=expected')->assertRedirectTo('/zh-Hant/login');
        $this->assertNull(session('microsoft_flow'));
        $this->assertFalse(auth()->loggedIn());
    }

    public function testLoginViewShowsMicrosoftButtonWhenEnabled(): void
    {
        service('settings')->set('MicrosoftOAuth.enabled', true);
        $this->assertStringContainsString('/en/microsoft/start', $this->get('/en/login')->response()->getBody());
    }

    public function testConnectionRequiresCurrentPassword(): void
    {
        $this->loginAs('superadmin');
        $this->post('/en/admin/profile/microsoft/connect', [csrf_token() => csrf_hash(), 'current_password' => 'incorrect'])->assertRedirectTo('/en/admin/profile');

        $this->assertSame(lang('Admin.microsoftPasswordInvalid'), session('alert')['message'] ?? null);
        $this->assertNull(auth()->user()->getIdentity(MicrosoftLinks::IDENTITY_TYPE));
        $this->assertNull(session('microsoft_flow'));
    }

    public function testConnectionFailureAfterCorrectPasswordReturnsToProfile(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('MicrosoftOAuth.enabled', false);

        $this->post('/en/admin/profile/microsoft/connect', [csrf_token() => csrf_hash(), 'current_password' => 'A-local-password-123!'])->assertRedirectTo('/en/admin/profile');

        $this->assertSame(lang('Admin.microsoftLoginFailed'), session('alert')['message'] ?? null);
        $this->assertNull(session('microsoft_flow'));
    }

    public function testOrdinaryUserCanReachMicrosoftConnection(): void
    {
        $this->loginAs('user');
        $this->assertFalse(auth()->user()->can('admin.access'));
        $this->assertTrue((new MicrosoftLinks())->isEligible(auth()->user()));
        service('settings')->set('MicrosoftOAuth.enabled', false);

        $this->post('/en/admin/profile/microsoft/connect', [csrf_token() => csrf_hash(), 'current_password' => 'A-local-password-123!'])->assertRedirectTo('/en/admin/profile');
        $this->assertSame(lang('Admin.microsoftLoginFailed'), session('alert')['message'] ?? null);
    }

    public function testConnectionWithMissingClientSecretReturnsToProfile(): void
    {
        $this->loginAs('superadmin');
        service('settings')->set('MicrosoftOAuth.enabled', true);
        service('settings')->set('MicrosoftOAuth.clientId', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        config('MicrosoftOAuth')->clientSecret = '';

        $this->post('/en/admin/profile/microsoft/connect', [csrf_token() => csrf_hash(), 'current_password' => 'A-local-password-123!'])->assertRedirectTo('/en/admin/profile');

        $this->assertSame(lang('Admin.microsoftLoginFailed'), session('alert')['message'] ?? null);
        $this->assertNull(session('microsoft_flow'));
    }

    public function testAlreadyLinkedAccountDoesNotReportWrongPassword(): void
    {
        $this->loginAs('superadmin');
        $user  = auth()->user();
        $links = new MicrosoftLinks();
        $this->assertTrue($links->bind($user, '11111111-2222-3333-4444-555555555555', 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff'));

        $this->post('/en/admin/profile/microsoft/connect', [csrf_token() => csrf_hash(), 'current_password' => 'A-local-password-123!'])->assertRedirectTo('/en/admin/profile');

        $this->assertSame(lang('Admin.microsoftLinked'), session('alert')['message'] ?? null);
        $this->assertNull(session('microsoft_flow'));
    }

    public function testBannedAccountCannotStartBinding(): void
    {
        $this->loginAs('superadmin');
        auth()->user()->ban();
        $this->post('/en/admin/profile/microsoft/connect', [csrf_token() => csrf_hash(), 'current_password' => 'A-local-password-123!'])->assertRedirectTo('/en/login');

        $this->assertNull(session('microsoft_flow'));
    }

    public function testApprovalLinksSelectedExistingUserWithoutAdminAccess(): void
    {
        $this->loginAs('superadmin');
        $target        = new AdminUser(['username' => 'microsofttarget']);
        $target->email = 'microsofttarget@example.com';
        $target->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($target);
        $target = $users->findById($users->getInsertID());
        $target->addGroup('user');
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $object = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
        $links->request($tenant, $object, 'microsoftsettings@example.com');

        $this->assertNull($links->findUser($tenant, $object));
        $this->assertTrue(auth()->user()->can('users.manage-admins'));
        $this->assertFalse($target->can('admin.access'));
        $this->assertTrue((new MicrosoftLinks())->isEligible($target));
        $requestId = $links->pending()[0]['id'];
        $this->assertStringContainsString('value="' . $target->id . '"', $this->get('/en/admin/settings/microsoft')->response()->getBody());
        $this->post('/en/admin/settings/microsoft/requests/' . $requestId . '/approve', [csrf_token() => csrf_hash(), 'user_id' => $target->id])->assertRedirect();

        $this->assertSame(lang('Admin.microsoftApproved'), session('alert')['message'] ?? null);
        $this->assertSame($target->id, $links->findUser($tenant, $object)?->id);
        $this->assertSame([], $links->pending());
        $this->assertFalse($links->bind($target, $tenant, $object));
    }

    public function testExistingMicrosoftBindingCannotBeReplacedByAnotherApproval(): void
    {
        $this->loginAs('superadmin');
        $user   = auth()->user();
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $first  = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
        $second = 'cccccccc-dddd-eeee-ffff-000000000000';

        $this->assertTrue($links->bind($user, $tenant, $first));
        $this->assertFalse($links->bind($user, $tenant, $second));
        $this->assertTrue($links->request($tenant, $second, str_repeat('x', 300)));
        $this->assertSame(255, mb_strlen($links->pending()[0]['email']));
        $this->assertFalse($links->approve((int) $links->pending()[0]['id'], $user));
        $this->assertNull($links->findUser($tenant, $second));
    }

    public function testExpiredApprovalRequestsAreRemoved(): void
    {
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $this->assertTrue($links->request($tenant, 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff', null));
        Database::connect()->table('microsoft_link_requests')->where('tenant_id', $tenant)->update(['expires_at' => '2000-01-01 00:00:00']);

        $this->assertTrue($links->request($tenant, 'cccccccc-dddd-eeee-ffff-000000000000', null));
        $this->assertCount(1, $links->pending());
    }

    public function testPendingRequestsAreLimitedPerTenant(): void
    {
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';

        for ($index = 0; $index < 10; $index++) {
            $this->assertTrue($links->request($tenant, sprintf('bbbbbbbb-cccc-dddd-eeee-%012x', $index), null));
        }

        $this->assertFalse($links->request($tenant, 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff', null));
        $this->assertTrue($links->request('22222222-3333-4444-5555-666666666666', 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff', null));
    }

    public function testOrdinaryAdminCannotApproveMicrosoftIdentity(): void
    {
        $this->loginAs('admin');
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $object = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
        $links->request($tenant, $object, null);

        $this->post('/en/admin/settings/microsoft/requests/' . $links->pending()[0]['id'] . '/approve', [csrf_token() => csrf_hash(), 'user_id' => auth()->user()->id])->assertRedirect();

        $this->assertNull($links->findUser($tenant, $object));
    }

    public function testDelegatedAdminCannotManageSuperadminMicrosoftIdentity(): void
    {
        $target        = new AdminUser(['username' => 'protectedmicrosoft']);
        $target->email = 'protectedmicrosoft@example.com';
        $target->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($target);
        $target = $users->findById($users->getInsertID());
        $target->addGroup('superadmin');

        $originalMatrix    = setting('AuthGroups.matrix');
        $matrix            = $originalMatrix;
        $matrix['admin'][] = 'users.manage-admins';
        $matrix['admin'][] = 'admin.settings';
        setting('AuthGroups.matrix', $matrix);

        try {
            $this->loginAs('admin');
            $this->assertTrue(auth()->user()->can('users.manage-admins'));

            $links   = new MicrosoftLinks();
            $tenant  = '11111111-2222-3333-4444-555555555555';
            $linked  = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
            $pending = 'cccccccc-dddd-eeee-ffff-000000000000';
            $own     = 'dddddddd-eeee-ffff-0000-111111111111';
            $this->assertTrue($links->bind($target, $tenant, $linked));
            $this->assertTrue($links->bind(auth()->user(), $tenant, $own));
            $this->assertTrue($links->request($tenant, $pending, null));
            $requestId = $links->pending()[0]['id'];
            $page      = $this->get('/en/admin/settings/microsoft');
            $page->assertOK();
            $this->assertStringContainsString('<option value="' . auth()->id() . '">', $page->response()->getBody());
            $this->assertStringContainsString('/users/' . auth()->id() . '/revoke', $page->response()->getBody());
            $this->assertStringNotContainsString('<option value="' . $target->id . '">', $page->response()->getBody());
            $this->assertStringNotContainsString('/users/' . $target->id . '/revoke', $page->response()->getBody());

            $this->post('/en/admin/settings/microsoft/requests/' . $requestId . '/approve', [csrf_token() => csrf_hash(), 'user_id' => $target->id])->assertStatus(404);
            $this->assertNull($links->findUser($tenant, $pending));
            $this->assertCount(1, $links->pending());

            $this->post('/en/admin/settings/microsoft/users/' . $target->id . '/revoke', [csrf_token() => csrf_hash()])->assertStatus(404);
            $this->assertSame($target->id, $links->findUser($tenant, $linked)?->id);
        } finally {
            setting('AuthGroups.matrix', $originalMatrix);
        }
    }

    public function testSuperadminCannotManageAnotherSuperadminMicrosoftIdentity(): void
    {
        $target        = new AdminUser(['username' => 'othersuperadmin']);
        $target->email = 'othersuperadmin@example.com';
        $target->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($target);
        $target = $users->findById($users->getInsertID());
        $target->addGroup('superadmin');
        $this->loginAs('superadmin');

        $links   = new MicrosoftLinks();
        $tenant  = '11111111-2222-3333-4444-555555555555';
        $linked  = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
        $pending = 'cccccccc-dddd-eeee-ffff-000000000000';
        $this->assertTrue($links->bind($target, $tenant, $linked));
        $this->assertTrue($links->request($tenant, $pending, null));
        $requestId = $links->pending()[0]['id'];
        $own       = 'dddddddd-eeee-ffff-0000-111111111111';
        $this->assertTrue($links->bind(auth()->user(), $tenant, $own));
        $page = $this->get('/en/admin/settings/microsoft');
        $page->assertOK();
        $this->assertStringContainsString('/users/' . auth()->id() . '/revoke', $page->response()->getBody());
        $this->assertStringNotContainsString('/users/' . $target->id . '/revoke', $page->response()->getBody());

        $this->post('/en/admin/settings/microsoft/requests/' . $requestId . '/approve', [csrf_token() => csrf_hash(), 'user_id' => $target->id])->assertStatus(404);
        $this->assertNull($links->findUser($tenant, $pending));
        $this->post('/en/admin/settings/microsoft/requests/' . $requestId . '/approve', [csrf_token() => csrf_hash(), 'user_id' => auth()->id()])->assertStatus(404);
        $this->assertCount(1, $links->pending());
        $this->post('/en/admin/settings/microsoft/users/' . $target->id . '/revoke', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->assertSame($target->id, $links->findUser($tenant, $linked)?->id);
    }

    public function testAdminCanRejectAndRevokeMicrosoftIdentity(): void
    {
        $this->loginAs('superadmin');
        $user   = auth()->user();
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $object = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
        $this->assertTrue($links->request($tenant, $object, null));

        $requestId = $links->pending()[0]['id'];
        $this->post('/en/admin/settings/microsoft/requests/' . $requestId . '/reject', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame([], $links->pending());
        $this->assertFalse($links->request($tenant, $object, null));
        $this->assertNull($links->findUser($tenant, $object));

        $this->assertTrue($links->bind($user, $tenant, $object));
        $this->post('/en/admin/settings/microsoft/users/' . $user->id . '/revoke', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertNull($links->findUser($tenant, $object));
    }

    public function testRevocationRemovesAllMicrosoftIdentitiesForUser(): void
    {
        $this->loginAs('superadmin');
        $user   = auth()->user();
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $first  = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
        $second = 'cccccccc-dddd-eeee-ffff-000000000000';

        $this->assertTrue($links->bind($user, $tenant, $first));
        model(UserIdentityModel::class)->insert([
            'user_id' => $user->id,
            'type'    => MicrosoftLinks::IDENTITY_TYPE,
            'secret'  => $tenant . ':' . $second,
        ]);

        $this->assertTrue($links->revoke($user));
        $this->assertNull($links->findUser($tenant, $first));
        $this->assertNull($links->findUser($tenant, $second));
    }

    public function testInactiveAdminCanBindAndReceiveApproval(): void
    {
        $this->loginAs('superadmin');
        $user   = auth()->user();
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $object = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';

        $this->assertFalse($user->active);
        $this->assertTrue($links->bind($user, $tenant, $object));
        $this->assertSame($user->id, $links->findUser($tenant, $object)?->id);
        $this->assertTrue($links->revoke($user));
        $this->assertTrue($links->request($tenant, 'cccccccc-dddd-eeee-ffff-000000000000', null));
        $this->assertTrue($links->approve((int) $links->pending()[0]['id'], $user));
        $this->assertSame($user->id, $links->findUser($tenant, 'cccccccc-dddd-eeee-ffff-000000000000')?->id);
    }

    public function testBannedAdminCannotBindOrReceiveApproval(): void
    {
        $this->loginAs('superadmin');
        $user   = auth()->user();
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $object = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
        $user->ban();

        $this->assertFalse($links->bind($user, $tenant, $object));
        $this->assertTrue($links->request($tenant, $object, null));
        $this->assertFalse($links->approve((int) $links->pending()[0]['id'], $user));
        $this->assertNull($links->findUser($tenant, $object));
    }

    public function testOrdinaryAdminCannotRevokeMicrosoftIdentity(): void
    {
        $this->loginAs('admin');
        $links  = new MicrosoftLinks();
        $tenant = '11111111-2222-3333-4444-555555555555';
        $object = 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff';
        $this->assertTrue($links->bind(auth()->user(), $tenant, $object));
        $this->post('/en/admin/settings/microsoft/users/' . auth()->user()->id . '/revoke', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(auth()->user()->id, $links->findUser($tenant, $object)?->id);
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
