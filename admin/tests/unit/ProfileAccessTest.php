<?php

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Composer\InstalledVersions;
use Config\Services;
use Geminus\Admin\Controllers\Profile;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\AvatarFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Libraries\TableLayoutAssertions;

/**
 * @internal
 */
final class ProfileAccessTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

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
        TableLayoutAssertions::assertTablesInCards($result->response()->getBody());
        $this->assertStringContainsString('src="/static/js/form-submission.js"', $result->response()->getBody());
        $this->assertStringContainsString('href="/static/css/theme.css"', $result->response()->getBody());
        $result->assertSee('profile@example.com');
        $result->assertSee(lang('Admin.noTokens'), 'h3');
        $this->assertStringNotContainsString('class="empty-action"', $result->response()->getBody());
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
        $this->assertMatchesRegularExpression('/<input\b[^>]*id="token-expires"[^>]*data-bs-toggle="datepicker"[^>]*data-bs-date-min="\d{4}-\d{2}-\d{2}"/', $result->response()->getBody());
        $this->assertStringContainsString('dateFormat: (date) =>', $result->response()->getBody());

        $this->get('/zh-Hans/admin/profile')->assertSee('至少 8 个字符，最多 255 个字符');
        $this->get('/zh-Hant/admin/profile')->assertSee('至少 8 個字元，最多 255 個字元');
        $this->get('/zh-Hans/admin/profile')->assertSee('上传头像');
        $this->get('/zh-Hant/admin/profile')->assertSee('上傳頭像');

        foreach (['zh-Hans', 'zh-Hant'] as $locale) {
            $localized = $this->get('/' . $locale . '/admin/profile');
            $localized->assertOK();
            $localized->assertSee(lang('Admin.noTokens'), 'h3');
            $this->assertStringNotContainsString('class="empty-action"', $localized->response()->getBody());
        }
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

    #[DataProvider('avatarSaveOutcomes')]
    public function testAvatarUploadPreservesFilesAccordingToSaveOutcome(bool $fails, bool $throws, bool $cleanupFails = false): void
    {
        $user        = new AdminUser(['username' => 'avatarreplacement']);
        $user->email = 'avatarreplacement@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);

        $directory = WRITEPATH . 'uploads/avatars/';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $oldName = 'old-avatar-' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($directory . $oldName, 'previous avatar');
        $user->avatar = $oldName;
        $users->save($user);

        $source = tempnam(sys_get_temp_dir(), 'avatar-upload-');
        $image  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==', true);
        file_put_contents($source, $image);
        $movedName        = null;
        $providerProperty = new ReflectionProperty(Auth::class, 'userProvider');

        try {
            $upload = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, 'avatar.png', 'image/png', filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['move', 'getName', 'isValid'])
                ->getMock();
            $upload->method('isValid')->willReturn(true);
            $upload->expects($this->once())->method('move')->willReturnCallback(static function (string $target, ?string $name) use ($source, &$movedName): bool {
                $movedName = $name;
                copy($source, $target . '/' . $name);

                return true;
            });
            $upload->method('getName')->willReturnCallback(static function () use (&$movedName): string {
                return $movedName ?? 'avatar.png';
            });
            $request = $this->getMockBuilder(IncomingRequest::class)
                ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
                ->onlyMethods(['getFile', 'getFileMultiple'])
                ->getMock();
            $request->expects($this->atLeastOnce())->method('getFile')->with('avatar')->willReturn($upload);
            $request->expects($this->atLeastOnce())->method('getFileMultiple')->with('avatar')->willReturn(null);
            Services::injectMock('request', $request);
            Services::resetSingle('validation');
            $storage = null;
            if ($cleanupFails) {
                $storage = AvatarFiles::storage(static fn (string $filename): bool => false);
            }
            $controller = new Profile($storage);
            $controller->initController($request, Services::response(null, false), Services::logger());
            if ($fails) {
                $provider = $this->getMockBuilder($users::class)->onlyMethods(['save'])->getMock();
                $provider->expects($this->once())->method('save')->willReturnCallback(static function () use ($throws): bool {
                    if ($throws) {
                        throw new RuntimeException('Simulated avatar save failure.');
                    }

                    return false;
                });
                $providerProperty->setValue(auth(), $provider);
            }

            $response = $controller->avatar();

            $this->assertSame('/en/admin/profile', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            $this->assertNotNull($movedName);
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{32}\.png\z/', $movedName);
            if ($fails) {
                $this->assertSame(lang('Admin.avatarFailed'), session('alert')['message']);
                $this->assertSame('danger', session('alert')['type']);
                $this->assertSame($oldName, $users->findById($user->id)->avatar);
                $this->assertSame($oldName, auth()->user()->avatar);
                $this->assertFileExists($directory . $oldName);
                $this->assertFileDoesNotExist($directory . $movedName);
            } else {
                $this->assertSame(lang('Admin.avatarSaved'), session('alert')['message']);
                $this->assertSame($movedName, $users->findById($user->id)->avatar);
                $this->assertSame($image, file_get_contents($directory . $movedName));
                if ($cleanupFails) {
                    $this->assertFileExists($directory . $oldName);
                    $this->assertLogged('error', 'Avatar file cleanup failed: RuntimeException');
                } else {
                    $this->assertFileDoesNotExist($directory . $oldName);
                }
            }
        } finally {
            $providerProperty->setValue(auth(), $users);
            Services::resetSingle('request');
            Services::resetSingle('validation');
            Services::resetSingle('logger');
            unlink($source);
            if (is_file($directory . $oldName)) {
                unlink($directory . $oldName);
            }
            if ($movedName !== null && is_file($directory . $movedName)) {
                unlink($directory . $movedName);
            }
        }
    }

    public static function avatarSaveOutcomes(): iterable
    {
        yield 'saved' => [false, false];

        yield 'save returns false' => [true, false];

        yield 'save throws' => [true, true];

        yield 'cleanup fails after save' => [false, false, true];
    }

    #[DataProvider('provideAvatarValidationRejectsInvalidFilesBeforeMoving')]
    public function testAvatarValidationRejectsInvalidFilesBeforeMoving(string $failure): void
    {
        $user        = new AdminUser(['username' => 'avatarvalidation']);
        $user->email = 'avatarvalidation@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);
        $directory = WRITEPATH . 'uploads/avatars/';
        $before    = glob($directory . '*');
        $source    = tempnam(sys_get_temp_dir(), 'avatar-invalid-');
        $image     = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==', true);
        $contents  = $failure === 'content' ? 'plain text' : $image;
        if ($failure === 'size') {
            $contents .= str_repeat("\0", 2 * 1024 * 1024);
        }
        file_put_contents($source, $contents);

        try {
            $upload = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, $failure === 'extension' ? 'avatar.gif' : 'avatar.png', 'image/png', filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid', 'move'])->getMock();
            $upload->method('isValid')->willReturn(true);
            $upload->expects($this->never())->method('move');
            $request = $this->getMockBuilder(IncomingRequest::class)
                ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
                ->onlyMethods(['getFile', 'getFileMultiple'])->getMock();
            $request->expects($this->atLeastOnce())->method('getFile')->with('avatar')->willReturn($upload);
            $request->expects($this->atLeastOnce())->method('getFileMultiple')->with('avatar')->willReturn(null);
            Services::injectMock('request', $request);
            Services::resetSingle('validation');
            $controller = new Profile();
            $controller->initController($request, Services::response(null, false), Services::logger());
            $response = $controller->avatar();
            $this->assertSame('/en/admin/profile', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            $this->assertArrayHasKey('avatar', session('avatar_errors'));
            $this->assertNull($users->findById($user->id)->avatar);
            $this->assertSame($before, glob($directory . '*'));
        } finally {
            Services::resetSingle('request');
            Services::resetSingle('validation');
            unlink($source);
        }
    }

    public static function provideAvatarValidationRejectsInvalidFilesBeforeMoving(): iterable
    {
        yield 'not an image' => ['content'];

        yield 'unsupported extension' => ['extension'];

        yield 'over 2 MB' => ['size'];
    }

    #[DataProvider('avatarSaveOutcomes')]
    public function testAvatarRemovalPreservesFileWhenSaveFails(bool $fails, bool $throws, bool $cleanupFails = false): void
    {
        $user        = new AdminUser(['username' => 'avatarremove']);
        $user->email = 'avatarremove@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user      = $users->findById($users->getInsertID());
        $directory = WRITEPATH . 'uploads/avatars/';
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $filename = 'remove-test-' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($directory . $filename, 'previous avatar');
        $user->avatar = $filename;
        $users->save($user);
        $user = $users->findById($user->id);
        auth()->login($user);
        $providerProperty = new ReflectionProperty(Auth::class, 'userProvider');

        try {
            if ($fails) {
                $provider = $this->getMockBuilder($users::class)->onlyMethods(['save'])->getMock();
                $provider->expects($this->once())->method('save')->willReturnCallback(static function () use ($throws): bool {
                    if ($throws) {
                        throw new RuntimeException('Simulated avatar removal failure.');
                    }

                    return false;
                });
                $providerProperty->setValue(auth(), $provider);
            }
            $storage = null;
            if ($cleanupFails) {
                $storage = AvatarFiles::storage(static fn (string $filename): bool => false);
            }
            $controller = new Profile($storage);
            $controller->initController(service('request'), Services::response(null, false), service('logger'));
            $response = $controller->removeAvatar();
            $this->assertSame('/en/admin/profile', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            if ($fails) {
                $this->assertSame(lang('Admin.avatarFailed'), session('alert')['message']);
                $this->assertSame($filename, $users->findById($user->id)->avatar);
                $this->assertSame($filename, auth()->user()->avatar);
                $this->assertFileExists($directory . $filename);
            } else {
                $this->assertSame(lang('Admin.avatarRemoved'), session('alert')['message']);
                $this->assertNull($users->findById($user->id)->avatar);
                if ($cleanupFails) {
                    $this->assertFileExists($directory . $filename);
                    $this->assertLogged('error', 'Avatar file cleanup failed: RuntimeException');
                } else {
                    $this->assertFileDoesNotExist($directory . $filename);
                }
            }
        } finally {
            $providerProperty->setValue(auth(), $users);
            Services::resetSingle('logger');
            if (is_file($directory . $filename)) {
                unlink($directory . $filename);
            }
        }
    }

    public function testAvatarRouteOnlyServesAssociatedSafeAvatars(): void
    {
        $directory = WRITEPATH . 'uploads/avatars/';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filename = 'serve-test-' . bin2hex(random_bytes(8)) . '.png';
        $filePath = $directory . $filename;
        $content  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==', true);
        file_put_contents($filePath, $content);
        $sentinelName = 'serve-sentinel-' . bin2hex(random_bytes(8)) . '.txt';
        $sentinelPath = WRITEPATH . 'uploads/' . $sentinelName;
        $sentinel     = 'private-' . bin2hex(random_bytes(16));
        file_put_contents($sentinelPath, $sentinel);
        $linkName = 'link-' . $filename;
        symlink($sentinelPath, $directory . $linkName);
        $blockedFiles = [];

        foreach (['documents', 'images', 'attachments'] as $type) {
            $blockedDirectory = WRITEPATH . 'uploads/' . $type . '/';
            if (! is_dir($blockedDirectory)) {
                mkdir($blockedDirectory, 0750, true);
            }
            $blockedFiles[$type] = $blockedDirectory . $filename;
            file_put_contents($blockedFiles[$type], $sentinel);
        }

        try {
            $user        = new AdminUser(['username' => 'fileviewer', 'avatar' => $filename]);
            $user->email = 'fileviewer@example.com';
            $user->setPassword('A-local-password-123!');
            $users = auth()->getProvider();
            $users->save($user);
            $user  = $users->findById($users->getInsertID());
            $route = '/admin/avatars/' . $user->id;
            $this->assertSame(base_url(ltrim($route, '/')), $user->getAvatarUrl());
            $this->get($route)->assertRedirect();
            auth()->login($user);
            $this->assertFalse($user->can('users.manage-admins'));

            $response = $this->get($route);
            $response->assertOK();
            $this->assertSame('image/png', $response->response()->getHeaderLine('Content-Type'));
            $this->assertSame($content, $response->response()->getBody());
            $this->assertStringContainsString('private', $response->response()->getHeaderLine('Cache-Control'));
            $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
            $this->assertSame('nosniff', $response->response()->getHeaderLine('X-Content-Type-Options'));
            $this->get('/admin/files/avatars/' . $filename)->assertStatus(404);

            foreach ($blockedFiles as $type => $blockedFile) {
                $blocked = $this->get('/admin/files/' . $type . '/' . $filename);
                $blocked->assertStatus(404);
                $this->assertStringNotContainsString($sentinel, $blocked->response()->getBody());
                $this->assertFileExists($blockedFile);
            }
            $this->get('/admin/avatars/99999999')->assertStatus(404);

            foreach ([null, 'missing-' . $filename, $linkName, '../' . $sentinelName] as $invalidAvatar) {
                $user         = $users->findById($user->id);
                $user->avatar = $invalidAvatar;
                $users->save($user);
                $blocked = $this->get($route);
                $blocked->assertStatus(404);
                $this->assertStringNotContainsString($sentinel, $blocked->response()->getBody());
                if ($invalidAvatar === null) {
                    $this->assertNull($user->getAvatarUrl());
                }
            }
        } finally {
            unlink($directory . $linkName);
            unlink($filePath);
            unlink($sentinelPath);

            foreach ($blockedFiles as $blockedFile) {
                unlink($blockedFile);
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
        TableLayoutAssertions::assertTablesInCards($result->response()->getBody());
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
        $this->assertSame(lang('Admin.futureExpiry'), session('token_errors')['expires']);
        $this->assertSame([], $user->accessTokens());
        $result = $this->withSession($_SESSION)->get('/en/admin/profile');
        $this->assertStringContainsString('value="2020-01-01"', $result->response()->getBody());
        $this->assertStringContainsString(esc(lang('Admin.futureExpiry')), $result->response()->getBody());
        $this->assertStringContainsString('id="token-expires-error"', $result->response()->getBody());
        $this->assertMatchesRegularExpression('/<input\b[^>]*id="token-expires"[^>]*aria-invalid="true"[^>]*aria-describedby="token-expires-error"/', $result->response()->getBody());
    }

    public function testUserCannotRevokeAnotherUsersToken(): void
    {
        $owner        = new AdminUser(['username' => 'tokenowner']);
        $owner->email = 'tokenowner@example.com';
        $owner->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($owner);
        $owner = $users->findById($users->getInsertID());
        $token = $owner->generateAccessToken('Owner token', ['*']);

        $other        = new AdminUser(['username' => 'tokenother']);
        $other->email = 'tokenother@example.com';
        $other->setPassword('A-local-password-123!');
        $users->save($other);
        auth()->login($users->findById($users->getInsertID()));

        $this->post('/en/admin/profile/tokens/' . $token->id . '/revoke', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(lang('Admin.tokenNotFound'), session('alert')['message']);
        $this->assertCount(1, $owner->accessTokens());
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

    public function testRejectedPasswordChangesPreserveExistingPassword(): void
    {
        $user        = new AdminUser(['username' => 'passwordfailures']);
        $user->email = 'passwordfailures@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);
        $originalHash = $user->getPasswordHash();

        foreach ([
            ['current_password' => 'wrong-password', 'new_password' => 'Another-local-password-456!', 'confirm_password' => 'Another-local-password-456!', 'error' => 'current_password'],
            ['current_password' => 'A-local-password-123!', 'new_password' => 'Another-local-password-456!', 'confirm_password' => 'not-matching', 'error' => 'confirm_password'],
            ['current_password' => 'A-local-password-123!', 'new_password' => 'short', 'confirm_password' => 'short', 'error' => 'new_password'],
        ] as $attempt) {
            $this->post('/en/admin/profile/password', [
                csrf_token()       => csrf_hash(),
                'current_password' => $attempt['current_password'],
                'new_password'     => $attempt['new_password'],
                'confirm_password' => $attempt['confirm_password'],
            ])->assertRedirect();

            $this->assertNotEmpty(session('password_errors')[$attempt['error']]);
            $this->assertSame($originalHash, $users->findById($user->id)->getPasswordHash());
        }
    }

    public function testAccountWithoutLocalPasswordCannotChangePassword(): void
    {
        $user  = new AdminUser(['username' => 'microsoftonly']);
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        auth()->login($user);
        $this->assertEmpty($user->getEmailIdentity()?->secret2);

        $this->post('/en/admin/profile/password', [
            csrf_token()       => csrf_hash(),
            'current_password' => 'A-local-password-123!',
            'new_password'     => 'Another-local-password-456!',
            'confirm_password' => 'Another-local-password-456!',
        ])->assertRedirect();

        $this->assertSame(lang('Admin.localOnly'), session('alert')['message']);
        $this->assertEmpty($users->findById($user->id)->getEmailIdentity()?->secret2);
    }
}
