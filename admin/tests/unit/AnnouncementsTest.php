<?php

use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\AuthGroups;
use Config\Services;
use Geminus\Admin\Config\AdminMenu;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Models\AttachmentModel;
use Modules\Announcements\Config\Registrar;
use Modules\Announcements\Controllers\AnnouncementAttachments;
use Modules\Announcements\Controllers\Announcements;
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

    public function testListReusesEmptyStateAndPaginationInBothLocales(): void
    {
        $this->loginAs('superadmin');

        foreach (['en', 'zh-Hans'] as $locale) {
            service('language')->setLocale($locale);
            $page = $this->get('/' . $locale . '/admin/announcements');
            $page->assertOK();
            $this->assertStringContainsString('empty-title', $page->response()->getBody());
            $this->assertStringContainsString('href="/' . $locale . '/admin/announcements/create"', $page->response()->getBody());
            $page->assertSee(lang('Announcements.empty'));
        }

        for ($index = 0; $index < 16; $index++) {
            (new AnnouncementModel())->insert(['title' => 'Page ' . $index, 'body' => 'Body']);
        }
        Services::resetSingle('pager');
        $page = $this->get('/en/admin/announcements?page=2');
        $page->assertOK();
        $this->assertStringContainsString('(16 - 16)', $page->response()->getBody());
        $this->assertStringNotContainsString('empty-title', $page->response()->getBody());
        $this->assertStringContainsString('announcements?page=1', $page->response()->getBody());
    }

    public function testListFiltersAndSortsWithSafeDefaults(): void
    {
        $this->loginAs('superadmin');
        $model = new AnnouncementModel();
        $model->insert(['title' => 'Alpha MATCH', 'body' => 'First']);
        $first = $model->getInsertID();
        $model->insert(['title' => 'Beta', 'body' => 'match in body']);
        $second = $model->getInsertID();
        $model->insert(['title' => 'Outside', 'body' => 'match']);
        $model->builder()->where('id', $first)->update(['created_at' => '2026-10-01 00:00:00']);
        $model->builder()->where('id', $second)->update(['created_at' => '2026-10-01 23:59:59']);
        $model->builder()->where('title', 'Outside')->update(['created_at' => '2026-10-02 00:00:00']);

        $page = $this->get('/en/admin/announcements?q=MaTcH&created_from=2026-10-01&created_to=2026-10-01&sort=title&direction=asc');
        $page->assertOK();
        $page->assertSee('Alpha MATCH');
        $page->assertSee('Beta');
        $page->assertDontSee('Outside');
        $body = $page->response()->getBody();
        $this->assertLessThan(strpos($body, 'Beta'), strpos($body, 'Alpha MATCH'));

        $page = $this->get('/en/admin/announcements?sort=DROP%20TABLE&direction=invalid&created_from=invalid');
        $page->assertOK();
        $page->assertSee('Outside');
        $this->assertSame(3, $model->countAllResults());
    }

    public function testCsvEndpointsRequirePermissionAndImportRequiresCsrf(): void
    {
        foreach (['import', 'template', 'export'] as $endpoint) {
            $this->get('/en/admin/announcements/' . $endpoint)->assertRedirect();
        }
        $this->loginAs('admin');

        foreach (['import', 'template', 'export'] as $endpoint) {
            $this->get('/en/admin/announcements/' . $endpoint)->assertRedirect();
        }
        $this->post('/en/admin/announcements/import', [csrf_token() => csrf_hash()])->assertRedirect();
        $this->assertSame(0, (new AnnouncementModel())->countAllResults());
        auth()->logout();
        $this->loginAs('superadmin');
        $this->post('/en/admin/announcements/import', [csrf_token() => csrf_hash()])->assertRedirectTo('/en/admin/announcements/import');
        $this->assertNotEmpty(session('errors.file'));
        $this->expectException(SecurityException::class);
        $this->post('/en/admin/announcements/import');
    }

    public function testCsvImportValidatesRowsAndRendersEscapedReport(): void
    {
        $this->loginAs('superadmin');
        $source = tempnam(sys_get_temp_dir(), 'announcement-csv-');
        file_put_contents($source, "title,body\n<script>Title</script>,Valid body\n,Invalid body\nLast,Last body\nExtra,Body,Column\n");

        try {
            $file = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, 'announcements.csv', null, filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid', 'getMimeType'])->getMock();
            $file->expects($this->once())->method('isValid')->willReturn(true);
            $file->method('getMimeType')->willReturn('text/csv');
            $controller = $this->csvController($file);
            $response   = $controller->import();
            $this->assertSame('/en/admin/announcements/import', parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            $this->assertSame(2, (new AnnouncementModel())->countAllResults());
            $report = session('announcement_import_report');
            $this->assertSame(['created', 'error', 'created', 'error'], array_column($report, 'result'));
            $this->assertSame([2, 3, 4, 5], array_column($report, 'row'));
            $this->assertNotEmpty($report[1]['errors']['title']);
            $this->assertSame('invalid', $report[3]['reason']);
            $page = $this->withSession(['announcement_import_report' => $report])->get('/en/admin/announcements/import');
            $page->assertOK();
            $page->assertSee('Import results');
            $page->assertSee('Last');
            $this->assertStringContainsString('&lt;script&gt;Title&lt;/script&gt;', $page->response()->getBody());
            $this->assertStringNotContainsString('<script>Title</script>', $page->response()->getBody());
            $this->assertStringNotContainsString('Admin.user', $page->response()->getBody());
        } finally {
            unlink($source);
        }
    }

    public function testCsvImportRejectsInvalidFilesBeforeWriting(): void
    {
        $this->loginAs('superadmin');
        $source = tempnam(sys_get_temp_dir(), 'announcement-invalid-');

        try {
            foreach ([
                ['announcements.txt', "title,body\nTitle,Body\n", 30, true],
                ['announcements.csv', "title,body\nTitle,Body\n", 1024 * 1024 + 1, true],
                ['announcements.csv', "body,title\nBody,Title\n", 30, true],
                ['announcements.csv', "title,body\n" . str_repeat("Title,Body\n", 501), 6000, true],
                ['announcements.csv', "title,body\nTitle,Body\n", 30, false],
                ['announcements.csv', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==', true), 70, true],
            ] as [$name, $content, $size, $valid]) {
                file_put_contents($source, $content);
                session()->remove('errors');
                $file = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$source, $name, null, $size, UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid'])->getMock();
                $file->expects($this->once())->method('isValid')->willReturn($valid);
                $this->csvController($file)->import();
                $this->assertNotEmpty(session('errors.file'));
                $this->assertSame(0, (new AnnouncementModel())->countAllResults());
            }
        } finally {
            unlink($source);
        }
    }

    public function testCsvExportUsesListFiltersAndProtectsSpreadsheetCells(): void
    {
        $this->loginAs('superadmin');
        $model = new AnnouncementModel();
        $model->insert(['title' => '=1+1', 'body' => 'Matching']);
        $model->builder()->update(['created_at' => '2026-10-01 23:59:59']);
        $model->insert(['title' => 'Excluded', 'body' => 'Matching']);
        $csv = $this->get('/en/admin/announcements/export?q=matching&created_to=2026-10-01');
        $csv->assertOK();
        $this->assertStringContainsString("'=1+1,Matching", $csv->response()->getBody());
        $this->assertStringNotContainsString('Excluded', $csv->response()->getBody());
        $this->assertStringContainsString('attachment;', $csv->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame('nosniff', $csv->response()->getHeaderLine('X-Content-Type-Options'));
        $this->assertStringContainsString('private', $csv->response()->getHeaderLine('Cache-Control'));
        $template = $this->get('/en/admin/announcements/template');
        $template->assertOK();
        $this->assertSame("title,body\n", str_replace("\r\n", "\n", $template->response()->getBody()));
    }

    public function testAttachmentRoutesRequirePermissionAndExistingAnnouncement(): void
    {
        $route = '/en/admin/announcements/999999/attachments';

        foreach ([false, true] as $loggedIn) {
            if ($loggedIn) {
                $this->loginAs('admin');
            }
            $this->get($route)->assertRedirect();
            $this->get($route . '/1')->assertRedirect();
            $this->post($route, [csrf_token() => csrf_hash()])->assertRedirect();
            $this->post($route . '/1/remove', [csrf_token() => csrf_hash()])->assertRedirect();
        }
        auth()->logout();
        $this->loginAs('superadmin');
        $this->get($route)->assertStatus(404);
        $this->get($route . '/1')->assertStatus(404);
        $this->post($route, [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->post($route . '/1/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
    }

    public function testAttachmentWritesRequireCsrf(): void
    {
        $this->loginAs('superadmin');

        foreach (['/en/admin/announcements/1/attachments', '/en/admin/announcements/1/attachments/1/remove'] as $route) {
            try {
                $this->post($route);
                $this->fail('Missing CSRF token must be rejected.');
            } catch (SecurityException $exception) {
                $this->assertSame(0, (new AttachmentModel())->countAllResults());
            }
        }
    }

    public function testAttachmentsAreScopedAndCanBeUploadedDownloadedAndRemoved(): void
    {
        $this->loginAs('superadmin');
        $model = new AnnouncementModel();
        $owner = (int) $model->insert(['title' => 'Owner', 'body' => 'Body']);
        $other = (int) $model->insert(['title' => 'Other', 'body' => 'Body']);
        $route = '/en/admin/announcements/' . $owner . '/attachments';
        $this->get($route)->assertOK();
        $this->post($route, [csrf_token() => csrf_hash()])->assertRedirectTo($route);
        $this->assertNotEmpty(session('attachment_errors.file'));
        $this->assertSame(0, (new AttachmentModel())->countAllResults());
        $source = tempnam(sys_get_temp_dir(), 'announcement-file-');
        file_put_contents($source, 'private announcement attachment');
        $storedPath = null;

        try {
            $upload = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, '<script>.txt', null, filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid', 'move'])->getMock();
            $upload->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
            $upload->expects($this->once())->method('move')->willReturnCallback(static function (string $target, ?string $name) use ($source, &$storedPath): bool {
                $storedPath = $target . '/' . $name;

                return copy($source, $storedPath);
            });
            $request = $this->getMockBuilder(IncomingRequest::class)
                ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
                ->onlyMethods(['getFile'])->getMock();
            $request->expects($this->once())->method('getFile')->with('file')->willReturn($upload);
            $controller = new AnnouncementAttachments();
            $controller->initController($request, Services::response(null, false), service('logger'));
            $response = $controller->upload($owner);
            $this->assertSame($route, parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
            $attachment = (new AttachmentModel())->first();
            $this->assertSame('announcement', $attachment['resource_type']);
            $this->assertSame($owner, (int) $attachment['resource_id']);
            $this->assertSame((int) auth()->id(), (int) $attachment['uploaded_by']);
            $this->assertFileExists($storedPath);
            $page = $this->get($route);
            $page->assertOK();
            $this->assertStringContainsString('&lt;script&gt;.txt', $page->response()->getBody());
            $this->assertStringNotContainsString('<script>.txt', $page->response()->getBody());
            $this->assertStringContainsString('action="' . $route . '/' . $attachment['id'] . '/remove"', $page->response()->getBody());
            $otherRoute = '/en/admin/announcements/' . $other . '/attachments';
            $this->get($otherRoute . '/' . $attachment['id'])->assertStatus(404);
            $this->post($otherRoute . '/' . $attachment['id'] . '/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
            $this->assertFileExists($storedPath);
            $otherPage = $this->get($otherRoute);
            $this->assertStringNotContainsString('&lt;script&gt;.txt', $otherPage->response()->getBody());
            $download = $this->get($route . '/' . $attachment['id']);
            $download->assertStatus(200);
            $this->assertInstanceOf(DownloadResponse::class, $download->response());
            $download->response()->buildHeaders();
            $this->assertStringContainsString('attachment;', $download->response()->getHeaderLine('Content-Disposition'));
            $this->assertStringContainsString('private', $download->response()->getHeaderLine('Cache-Control'));
            $this->assertSame('nosniff', $download->response()->getHeaderLine('X-Content-Type-Options'));
            $this->post($route . '/' . $attachment['id'] . '/remove', [csrf_token() => csrf_hash()])->assertRedirectTo($route);
            $this->assertFileDoesNotExist($storedPath);
            $this->assertNull((new AttachmentModel())->find($attachment['id']));
            $this->get($route . '/' . $attachment['id'])->assertStatus(404);
        } finally {
            unlink($source);
            if ($storedPath !== null && is_file($storedPath)) {
                unlink($storedPath);
            }
        }
    }

    public function testUserAttachmentWithMatchingResourceIdIsNotAccessible(): void
    {
        $this->loginAs('superadmin');
        $owner      = (int) (new AnnouncementModel())->insert(['title' => 'Owner', 'body' => 'Body']);
        $attachment = (int) (new AttachmentModel())->insert([
            'resource_type' => 'user', 'resource_id' => $owner,
            'filename'      => bin2hex(random_bytes(16)) . '.txt', 'original_name' => 'user-only.txt',
            'mime_type'     => 'text/plain', 'size_bytes' => 4, 'uploaded_by' => auth()->id(),
        ]);
        $route = '/en/admin/announcements/' . $owner . '/attachments';
        $page  = $this->get($route);
        $page->assertDontSee('user-only.txt');
        $this->get($route . '/' . $attachment)->assertStatus(404);
        $this->post($route . '/' . $attachment . '/remove', [csrf_token() => csrf_hash()])->assertStatus(404);
        $this->assertNotNull((new AttachmentModel())->find($attachment));
    }

    public function testFilteredPaginationAndSortLinksPreserveOnlySupportedParameters(): void
    {
        $this->loginAs('superadmin');
        $model = new AnnouncementModel();

        for ($index = 0; $index < 16; $index++) {
            $model->insert(['title' => 'Matching ' . chr(65 + $index), 'body' => 'Body']);
        }
        $model->builder()->update(['created_at' => '2026-10-01 12:00:00']);
        Services::resetSingle('pager');
        $page = $this->get('/en/admin/announcements?q=matching&created_from=2026-10-01&sort=title&direction=asc&page=2&ignored=unsafe');
        $page->assertOK();
        $page->assertSee('(16 - 16)');
        $document = new DOMDocument();
        @$document->loadHTML($page->response()->getBody());
        $xpath = new DOMXPath($document);
        $links = $xpath->query('//a[contains(@href, "announcements?")]');
        $this->assertGreaterThanOrEqual(1, $links->length);

        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $parameters);
            $this->assertSame('matching', $parameters['q']);
            $this->assertSame('2026-10-01', $parameters['created_from']);
            $this->assertArrayNotHasKey('ignored', $parameters);
        }
        $sortForms = $xpath->query('//th/form');
        $this->assertSame(2, $sortForms->length);

        foreach ($sortForms as $form) {
            $this->assertSame('matching', $xpath->query('.//input[@name="q"]', $form)->item(0)->getAttribute('value'));
            $this->assertSame('2026-10-01', $xpath->query('.//input[@name="created_from"]', $form)->item(0)->getAttribute('value'));
            $this->assertSame(0, $xpath->query('.//input[@name="page" or @name="ignored"]', $form)->length);
        }
        $empty = $this->get('/en/admin/announcements?q=0');
        $empty->assertOK();
        $empty->assertSee('No matching announcements.');
    }

    public function testCsvExportRejectsMoreThanTenThousandRows(): void
    {
        $this->loginAs('superadmin');
        (new AnnouncementModel())->insertBatch(array_fill(0, 10001, ['title' => 'Bulk', 'body' => 'Body']));
        $result = $this->get('/en/admin/announcements/export');
        $result->assertStatus(413);
        $this->assertSame(lang('Admin.exportLimit'), $result->response()->getBody());
    }

    public function testRepeatedCsvImportCreatesNewRecordsWithOriginalFields(): void
    {
        $this->loginAs('superadmin');
        $source = tempnam(sys_get_temp_dir(), 'announcement-repeat-');
        file_put_contents($source, "title,body\nRepeated title,Original body\n");

        try {
            for ($iteration = 1; $iteration <= 2; $iteration++) {
                $file = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$source, 'announcements.csv', null, filesize($source), UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid'])->getMock();
                $file->expects($this->once())->method('isValid')->willReturn(true);
                $this->csvController($file)->import();
                $this->assertSame('created', session('announcement_import_report')[0]['result']);
                $rows = (new AnnouncementModel())->findAll();
                $this->assertCount($iteration, $rows);

                foreach ($rows as $row) {
                    $this->assertSame('Repeated title', $row['title']);
                    $this->assertSame('Original body', $row['body']);
                }
            }
        } finally {
            unlink($source);
        }
    }

    public function testCsvImportAcceptsExactlyFiveHundredRowsAndOneMegabyte(): void
    {
        $this->loginAs('superadmin');
        $source = tempnam(sys_get_temp_dir(), 'announcement-limit-');
        $csv    = "title,body\n";

        for ($index = 0; $index < 500; $index++) {
            $csv .= str_repeat('A', $index === 0 ? 128 : ($index === 1 ? 127 : 95)) . ',' . str_repeat('B', 2000) . "\n";
        }
        $this->assertSame(1024 * 1024, strlen($csv));
        file_put_contents($source, $csv);

        try {
            $file = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, 'announcements.csv', null, filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid'])->getMock();
            $file->expects($this->once())->method('isValid')->willReturn(true);
            $this->csvController($file)->import();
            $this->assertSame(500, (new AnnouncementModel())->countAllResults());
            $report = session('announcement_import_report');
            $this->assertCount(500, $report);
            $this->assertSame(['created'], array_values(array_unique(array_column($report, 'result'))));
            $this->assertSame(501, $report[499]['row']);
        } finally {
            unlink($source);
        }
    }

    public function testExportPreservesOrderingAndDateBoundsWithoutPagination(): void
    {
        $this->loginAs('superadmin');
        $model = new AnnouncementModel();

        for ($index = 15; $index >= 0; $index--) {
            $model->insert(['title' => sprintf('Selected %02d', $index), 'body' => 'Matching']);
        }
        $model->builder()->update(['created_at' => '2026-10-01 12:00:00']);
        $before = $model->insert(['title' => 'Selected Before', 'body' => 'Matching']);
        $after  = $model->insert(['title' => 'Selected After', 'body' => 'Matching']);
        $model->builder()->where('id', $before)->update(['created_at' => '2026-09-30 23:59:59']);
        $model->builder()->where('id', $after)->update(['created_at' => '2026-10-02 00:00:00']);
        $parameters = '?q=selected&created_from=2026-10-01&created_to=2026-10-01&sort=title&direction=asc&page=2';
        Services::resetSingle('pager');
        $page = $this->get('/en/admin/announcements' . $parameters);
        $page->assertOK();
        $page->assertDontSee('Selected Before');
        $page->assertDontSee('Selected After');
        $page->assertSee('Selected 15');
        $page->assertDontSee('Selected 00');
        $export = $this->get('/en/admin/announcements/export' . $parameters);
        $export->assertOK();
        $stream = fopen('php://temp', 'w+b');

        try {
            fwrite($stream, $export->response()->getBody());
            rewind($stream);
            $this->assertSame(['title', 'body'], fgetcsv($stream, escape: ''));

            for ($index = 0; $index < 16; $index++) {
                $this->assertSame([sprintf('Selected %02d', $index), 'Matching'], fgetcsv($stream, escape: ''));
            }
            $this->assertFalse(fgetcsv($stream, escape: ''));
        } finally {
            fclose($stream);
        }
    }

    public function testCsvExportAcceptsExactlyTenThousandRows(): void
    {
        $this->loginAs('superadmin');
        (new AnnouncementModel())->insertBatch(array_fill(0, 10000, ['title' => 'Bulk', 'body' => 'Body']));
        $result = $this->get('/en/admin/announcements/export');
        $result->assertOK();
        $this->assertSame(10000, substr_count($result->response()->getBody(), "Bulk,Body\n"));
    }

    private function csvController(UploadedFile $file): Announcements
    {
        $request = $this->getMockBuilder(IncomingRequest::class)
            ->setConstructorArgs([config('App'), service('uri'), null, new UserAgent()])
            ->onlyMethods(['getFile'])->getMock();
        $request->expects($this->once())->method('getFile')->with('file')->willReturn($file);
        $controller = new Announcements();
        $controller->initController($request, Services::response(null, false), service('logger'));

        return $controller;
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'example' . $group]);
        $user->email = 'example' . $group . '@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }
}
