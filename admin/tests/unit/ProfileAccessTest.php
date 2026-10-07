<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Composer\InstalledVersions;
use Geminus\Admin\Entities\AdminUser;

/**
 * @internal
 */
final class ProfileAccessTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    public function testUserFormatsDateTimeInPreferredTimezoneWithoutMutatingSource(): void
    {
        $user           = new AdminUser(['timezone' => 'America/New_York']);
        $winterDateTime = new DateTimeImmutable('2026-01-01 00:30:00 UTC');
        $summerDateTime = new DateTimeImmutable('2026-07-01 00:30:00 UTC');

        $this->assertSame('2025-12-31 19:30:00', $user->formatDateTime($winterDateTime));
        $this->assertSame('2026-06-30 20:30', $user->formatDateTime($summerDateTime, 'Y-m-d H:i'));
        $this->assertSame('2026-01-01 00:30:00', $winterDateTime->format('Y-m-d H:i:s'));
        $this->assertNull($user->formatDateTime(null));
    }

    public function testProfileRequiresAuthentication(): void
    {
        $result = $this->get('/en/admin/profile');

        $result->assertRedirect();
    }

    public function testLanguageSwitchRequiresAuthenticationAndCsrf(): void
    {
        $this->post('/en/admin/profile/language', [
            csrf_token() => csrf_hash(),
            'language'   => 'zh-Hans',
            'return'     => '/en/admin/profile',
        ])->assertRedirect();

        $this->expectException(SecurityException::class);
        $this->post('/en/admin/profile/language', ['language' => 'zh-Hans']);
    }

    public function testTokenCreationRequiresAuthentication(): void
    {
        $result = $this->post('/en/admin/profile/tokens', [
            csrf_token() => csrf_hash(),
            'name'       => 'Test',
            'expires'    => '2030-01-01',
        ]);

        $result->assertRedirect();
    }

    public function testAvatarChangesRequireAuthentication(): void
    {
        $this->post('/en/admin/profile/avatar', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->post('/en/admin/profile/avatar/remove', [csrf_token() => csrf_hash()])->assertRedirect();
    }

    public function testAuthenticatedUserCanViewProfile(): void
    {
        $user        = new AdminUser(['username' => 'profiletest']);
        $user->email = 'profile@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $result = $this->get('/en/admin/profile');

        $result->assertOK();
        $this->assertStringContainsString('src="/static/js/form-submission.js"', $result->response()->getBody());
        $this->assertStringContainsString('href="/static/css/theme.css"', $result->response()->getBody());
        $result->assertSee('profile@example.com');
        $result->assertSee('English', 'option');
        $result->assertSee('简体中文', 'option');
        $result->assertSee('繁體中文', 'option');
        $result->assertSee('Asia/Shanghai');
        $result->assertDontSee('Europe/Paris');

        foreach (['profile-avatar', 'profile-username', 'profile-language', 'profile-timezone', 'current_password', 'new_password', 'confirm_password', 'token-name', 'token-expires'] as $inputId) {
            $this->assertStringContainsString('class="form-label required" for="' . $inputId . '"', $result->response()->getBody());
        }
        $this->assertStringContainsString('aria-describedby="profile-username-hint"', $result->response()->getBody());
        $this->assertStringContainsString('id="profile-username-hint" class="form-text">' . lang('Admin.usernameHint'), $result->response()->getBody());
        $this->assertStringContainsString('id="profile-timezone" name="timezone" class="form-select" required', $result->response()->getBody());
        $this->assertStringContainsString('class="form-label" for="profile-email"', $result->response()->getBody());
        $result->assertSee('JPEG, PNG or WebP, up to 2 MB.');
        $this->assertStringContainsString('enctype="multipart/form-data"', $result->response()->getBody());
        $this->assertStringContainsString('avatar-xl', $result->response()->getBody());
        $this->assertStringContainsString('>pr</span>', $result->response()->getBody());
        $result->assertSee('Use at least 8 characters (up to 255).');
        $this->assertStringContainsString('aria-describedby="new-password-hint"', $result->response()->getBody());
        $this->assertStringContainsString('id="new-password-hint"', $result->response()->getBody());
        $this->assertStringContainsString('dist/libs/vanilla-calendar-pro/index.js', $result->response()->getBody());
        $this->assertStringContainsString('data-bs-toggle="datepicker" data-bs-date-min="', $result->response()->getBody());
        $this->assertStringContainsString('dateFormat: (date) =>', $result->response()->getBody());

        $this->get('/zh-Hans/admin/profile')->assertSee('至少 8 个字符，最多 255 个字符');
        $this->get('/zh-Hant/admin/profile')->assertSee('至少 8 個字元，最多 255 個字元');
        $this->get('/zh-Hans/admin/profile')->assertSee('上传头像');
        $this->get('/zh-Hant/admin/profile')->assertSee('上傳頭像');
    }

    public function testProfileLanguagePreferenceChangesRedirectLocale(): void
    {
        $user        = new AdminUser(['username' => 'preferencetest']);
        $user->email = 'preference@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $result = $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => $user->username,
            'language'   => 'zh-Hans',
            'timezone'   => 'Asia/Shanghai',
        ]);

        $result->assertRedirect();
        $this->assertSame('/zh-Hans/admin/profile', parse_url($result->response()->getHeaderLine('Location'), PHP_URL_PATH));
        $this->assertSame('zh-Hans', $users->findById($user->id)->language);
        $this->get('/zh-Hans/admin/profile')->assertSee('上传头像');
    }

    public function testProfileUsernameUsesConfiguredRules(): void
    {
        $user        = new AdminUser(['username' => 'profileoriginal']);
        $user->email = 'profilerules@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'profile_invalid',
            'language'   => 'en',
            'timezone'   => 'Asia/Shanghai',
        ])->assertRedirect();
        $this->assertArrayHasKey('username', session('profile_errors'));
        $this->assertSame('profileoriginal', $users->findById($user->id)->username);

        $result = $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'profile.valid',
            'language'   => 'en',
            'timezone'   => 'Asia/Shanghai',
        ]);
        $result->assertRedirect();
        $this->assertSame('profile.valid', $users->findById($user->id)->username);
    }

    public function testProfileRejectsCaseInsensitiveUsernameConflictButAllowsOwnCaseChange(): void
    {
        $users        = auth()->getProvider();
        $other        = new AdminUser(['username' => 'Taken.Name']);
        $other->email = 'profiletaken@example.com';
        $other->setPassword('A-local-password-123!');
        $users->save($other);

        $user        = new AdminUser(['username' => 'profileowner']);
        $user->email = 'profileowner@example.com';
        $user->setPassword('A-local-password-123!');
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'taken.name',
            'language'   => 'en',
            'timezone'   => 'Asia/Shanghai',
        ])->assertRedirect();
        $this->assertSame(lang('Admin.usernameTaken'), session('profile_errors.username'));
        $this->assertSame('profileowner', $users->findById($user->id)->username);

        $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'PROFILEOWNER',
            'language'   => 'en',
            'timezone'   => 'Asia/Shanghai',
        ])->assertRedirect();
        $this->assertSame('PROFILEOWNER', $users->findById($user->id)->username);

        $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'PROFILEOWNER',
            'language'   => 'zh-Hans',
            'timezone'   => 'Asia/Shanghai',
        ])->assertRedirect();
        $this->assertSame('zh-Hans', $users->findById($user->id)->language);
    }

    public function testProfileKeepsLegacyUsernameWhenOnlyUpdatingPreferences(): void
    {
        $user        = new AdminUser(['username' => 'legacy_name']);
        $user->email = 'legacyprofile@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'legacy_name',
            'language'   => 'zh-Hans',
            'timezone'   => 'Asia/Shanghai',
        ])->assertRedirect();

        $updated = $users->findById($user->id);
        $this->assertSame('legacy_name', $updated->username);
        $this->assertSame('zh-Hans', $updated->language);
    }

    public function testLanguageSelectorSavesPreferenceAndKeepsCurrentPage(): void
    {
        $user        = new AdminUser(['username' => 'selectortest']);
        $user->email = 'selector@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $page = $this->get('/en/admin/profile');
        $this->assertStringContainsString('name="return" value="/en/admin/profile"', $page->response()->getBody());
        $this->assertStringContainsString('name="language" value="zh-Hant"', $page->response()->getBody());
        $this->assertSame(3, substr_count($page->response()->getBody(), 'name="language" value="zh-Hant"'));
        $this->assertSame(1, substr_count($page->response()->getBody(), 'aria-label="Open language selector"'));
        $this->assertMatchesRegularExpression('/<div class="d-lg-none">.*?name="language" value="zh-Hant"/s', $page->response()->getBody());

        $result = $this->post('/en/admin/profile/language', [
            csrf_token() => csrf_hash(),
            'language'   => 'zh-Hant',
            'return'     => '/en/admin/profile',
        ]);

        $result->assertRedirect();
        $this->assertSame('/zh-Hant/admin/profile', parse_url($result->response()->getHeaderLine('Location'), PHP_URL_PATH));
        $this->assertSame('zh-Hant', $users->findById($user->id)->language);
        $this->get('/zh-Hant/admin/profile')->assertSee('上傳頭像');
    }

    public function testHomeUsesSavedLanguagePreference(): void
    {
        $user        = new AdminUser(['username' => 'homepagepref']);
        $user->email = 'homepage@example.com';
        $user->setPassword('A-local-password-123!');
        $user->language = 'zh-Hans';
        $users          = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $result = $this->get('/');

        $result->assertRedirect();
        $this->assertSame('/zh-Hans/admin/dashboard', parse_url($result->response()->getHeaderLine('Location'), PHP_URL_PATH));
        $this->get('/en/admin/dashboard')->assertSee('Dashboard');
    }

    public function testLanguageSwitchRejectsInvalidLocaleAndExternalReturn(): void
    {
        $user        = new AdminUser(['username' => 'invalidlocale']);
        $user->email = 'invalidlocale@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $this->post('/en/admin/profile/language', [
            csrf_token() => csrf_hash(),
            'language'   => 'invalid',
            'return'     => '/en/admin/profile',
        ])->assertRedirect();
        $this->assertSame($user->language, $users->findById($user->id)->language);

        $result = $this->post('/en/admin/profile/language', [
            csrf_token() => csrf_hash(),
            'language'   => 'zh-Hans',
            'return'     => '//outside.example/path',
        ]);
        $result->assertRedirect();
        $this->assertSame('/zh-Hans/admin/dashboard', parse_url($result->response()->getHeaderLine('Location'), PHP_URL_PATH));
        $this->assertSame('zh-Hans', $users->findById($user->id)->language);

        $result = $this->post('/en/admin/profile/language', [
            csrf_token() => csrf_hash(),
            'language'   => 'en',
            'return'     => ['en', 'admin', 'profile'],
        ]);
        $result->assertRedirect();
        $this->assertSame('/en/admin/dashboard', parse_url($result->response()->getHeaderLine('Location'), PHP_URL_PATH));
    }

    public function testAvatarUploadRejectsMissingFileAndRemovalClearsAvatar(): void
    {
        $user        = new AdminUser(['username' => 'avatartest']);
        $user->email = 'avatar@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $this->post('/en/admin/profile/avatar', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertArrayHasKey('avatar', session('avatar_errors'));
        $this->assertNull($users->findById($user->id)->avatar);

        $avatarDirectory = WRITEPATH . 'uploads/avatars/';
        if (! is_dir($avatarDirectory)) {
            mkdir($avatarDirectory, 0777, true);
        }

        $filename = 'avatar-test-' . bin2hex(random_bytes(8)) . '.png';
        $filePath = $avatarDirectory . $filename;
        file_put_contents($filePath, 'test');

        try {
            $user->avatar = $filename;
            $users->save($user);
            auth()->login($users->findById($user->id));

            $result = $this->withSession($_SESSION)->get('/en/admin/profile');
            $result->assertSee('Remove avatar');
            $this->assertStringContainsString('aria-label="avatartest"', $result->response()->getBody());
            $this->assertStringNotContainsString('>av</span>', $result->response()->getBody());
            $this->post('/en/admin/profile/avatar/remove', [csrf_token() => csrf_hash()])->assertRedirect();

            $this->assertNull($users->findById($user->id)->avatar);
            $this->assertFileDoesNotExist($filePath);
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testAuthenticatedUserCanCreateAndRevokeToken(): void
    {
        $user        = new AdminUser(['username' => 'tokentest']);
        $user->email = 'token@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $result = $this->post('/en/admin/profile/tokens', [
            csrf_token() => csrf_hash(),
            'name'       => 'Test key',
            'expires'    => '2030-01-01',
        ]);

        $result->assertRedirect();
        $tokens = $user->accessTokens();
        $this->assertCount(1, $tokens);
        $rawToken = session('alert')['detail'];

        $result = $this->withSession($_SESSION)->get('/en/admin/profile');
        $result->assertSee(lang('Admin.tokenOnce'));
        $result->assertSee($rawToken);

        $result = $this->post('/en/admin/profile/tokens/' . $tokens[0]->id . '/revoke', [
            csrf_token() => csrf_hash(),
        ]);

        $result->assertRedirect();
        $this->assertSame([], $user->accessTokens());
        $this->withSession($_SESSION)->get('/en/admin/profile')->assertSee(lang('Admin.tokenRevoked'));

        $this->post('/en/admin/profile/tokens', [
            csrf_token() => csrf_hash(),
            'name'       => 'Expired key',
            'expires'    => '2020-01-01',
        ])->assertRedirect();
        $result = $this->withSession($_SESSION)->get('/en/admin/profile');
        $this->assertStringContainsString('value="2020-01-01"', $result->response()->getBody());
    }

    public function testValidationErrorsUseRequestedLocale(): void
    {
        $user        = new AdminUser(['username' => 'localizedtest']);
        $user->email = 'localized@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        auth()->login($users->findById($users->getInsertID()));

        $this->post('/zh-Hans/admin/profile/tokens', [
            csrf_token() => csrf_hash(),
            'name'       => 'Test key',
            'expires'    => '2030/1/1',
        ])->assertRedirect();

        $this->assertSame('zh-Hans', service('request')->getLocale());
        $this->assertSame('zh-Hans', service('language')->getLocale());
        $this->assertSame('有效期至（UTC）必须是有效的日期。', session('token_errors')['expires']);

        $this->post('/zh-Hant/admin/profile/tokens', [
            csrf_token() => csrf_hash(),
            'name'       => 'Test key',
            'expires'    => '2030/1/1',
        ])->assertRedirect();
        $this->assertSame('有效期限（UTC）必須是有效的日期。', session('token_errors')['expires']);

        $this->post('/zh-Hant/admin/profile/tokens', [
            csrf_token() => csrf_hash(),
            'name'       => '',
            'expires'    => '2030-01-01',
        ])->assertRedirect();
        $this->assertSame('請填寫金鑰名稱。', session('token_errors')['name']);

        $this->post('/zh-Hans/admin/profile/password', [
            csrf_token()       => csrf_hash(),
            'current_password' => 'A-local-password-123!',
            'new_password'     => 'Another-local-password-456!',
            'confirm_password' => 'different',
        ])->assertRedirect();
        $this->assertSame('确认新密码与新密码不一致。', session('password_errors')['confirm_password']);

        $this->post('/en/admin/profile/tokens', [
            csrf_token() => csrf_hash(),
            'name'       => 'Test key',
            'expires'    => '2030/1/1',
        ])->assertRedirect();
        $this->assertSame('The Expires on (UTC) field must contain a valid date.', session('token_errors')['expires']);
    }

    public function testChineseValidationLanguagesCoverAllFrameworkRules(): void
    {
        $framework = require SYSTEMPATH . 'Language/en/Validation.php';

        foreach (['zh-Hans' => 'zh-CN', 'zh-Hant' => 'zh-TW'] as $locale => $packageLocale) {
            $translated = require APPPATH . 'Language/' . $locale . '/Validation.php';
            $package    = require InstalledVersions::getInstallPath('codeigniter4/translations') . '/Language/' . $packageLocale . '/Validation.php';

            $this->assertEqualsCanonicalizing(array_keys($framework), array_keys($translated));
            $this->assertSame($package['alpha'], $translated['alpha']);
        }
    }

    public function testAuthenticatedUserCanUpdateDetailsAndPassword(): void
    {
        $user        = new AdminUser(['username' => 'detailstest']);
        $user->email = 'details@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $result = $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'newdetails',
            'language'   => 'zh-Hans',
            'timezone'   => 'Asia/Shanghai',
        ]);

        $result->assertRedirect();
        $updated = $users->findById($user->id);
        $this->assertSame('newdetails', $updated->username);
        $this->assertSame('Asia/Shanghai', $updated->timezone);
        $this->withSession($_SESSION)->get('/en/admin/profile')->assertSee(lang('Admin.profileSaved'));

        $result = $this->post('/en/admin/profile', [
            csrf_token() => csrf_hash(),
            'username'   => 'newdetails',
            'language'   => 'zh-Hans',
            'timezone'   => 'Europe/Paris',
        ]);

        $result->assertRedirect();
        $this->assertSame('Asia/Shanghai', $users->findById($user->id)->timezone);

        $result = $this->post('/en/admin/profile/password', [
            csrf_token()       => csrf_hash(),
            'current_password' => 'A-local-password-123!',
            'new_password'     => 'Another-local-password-456!',
            'confirm_password' => 'Another-local-password-456!',
        ]);

        $result->assertRedirect();
        $this->assertTrue(service('passwords')->verify('Another-local-password-456!', $users->findById($user->id)->getPasswordHash()));
    }
}
