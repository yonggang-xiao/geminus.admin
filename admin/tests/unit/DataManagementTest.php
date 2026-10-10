<?php

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Model;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Geminus\Admin\Libraries\DataManagement\Attachments;
use Geminus\Admin\Libraries\DataManagement\Csv;
use Geminus\Admin\Libraries\DataManagement\CsvImport;
use Geminus\Admin\Libraries\DataManagement\ListQuery;
use Geminus\Admin\Libraries\DataManagement\UploadHistory;
use Geminus\Admin\Libraries\DataManagement\UploadSource;
use Geminus\Admin\Libraries\DataManagement\UploadStorage;
use Geminus\Admin\Models\AttachmentModel;
use Modules\Announcements\Libraries\AnnouncementUploadSource;
use Modules\Announcements\Models\AnnouncementModel;

/**
 * @internal
 */
final class DataManagementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace;

    public function testUploadHistoryCombinesVisibleSourcesWithoutMixingDescriptions(): void
    {
        $database   = db_connect();
        $resourceId = (int) (new AnnouncementModel())->insert(['title' => 'Shared ID', 'body' => 'Body', 'status' => 'draft']);
        $model      = new AttachmentModel();
        $ids        = [];

        foreach ([['first', 1], ['second', 1], ['second', 2], ['blocked', 1], ['unknown', 1]] as [$type, $uploaderId]) {
            $ids[] = (int) $model->insert(['resource_type' => $type, 'resource_id' => $resourceId, 'filename' => bin2hex(random_bytes(16)) . '.txt', 'original_name' => $type . '.txt', 'mime_type' => 'text/plain', 'size_bytes' => 7, 'uploaded_by' => $uploaderId]);
        }

        $sources = [];

        foreach (['first', 'second'] as $type) {
            $source = $this->createStub(UploadSource::class);
            $source->method('visibleResources')->willReturnCallback(static fn () => $database->table('example_announcements')->select('id'));
            $source->method('describe')->willReturn([$resourceId => ['label' => $type, 'title' => $type . ' record']]);
            $sources[$type] = $source;
        }

        $blocked = $this->createStub(UploadSource::class);
        $blocked->method('visibleResources')->willReturn(null);
        $sources = ['blocked' => $blocked] + $sources;
        $result  = (new UploadHistory(new AttachmentModel(), $sources))->paginate(1, new User());
        $this->assertSame([$ids[1], $ids[0]], array_map(static fn (array $item): int => (int) $item['id'], $result['attachments']));
        $this->assertSame(['second', 'first'], array_column(array_column($result['attachments'], 'source'), 'label'));
        $this->assertSame(2, $result['pager']->getTotal());
        $result = (new UploadHistory(new AttachmentModel(), ['second' => $blocked, 'first' => $sources['first']]))->paginate(1, new User());
        $this->assertSame([$ids[0]], array_map(static fn (array $item): int => (int) $item['id'], $result['attachments']));
        $this->assertSame(1, $result['pager']->getTotal());
    }

    public function testUploadHistoryRejectsInvalidSourcesAndRequests(): void
    {
        $source = $this->createStub(UploadSource::class);

        foreach ([['invalid/type' => $source], ['first' => new stdClass()], [1 => $source]] as $sources) {
            try {
                new UploadHistory(new AttachmentModel(), $sources);
                $this->fail('Invalid sources must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Invalid upload history source.', $exception->getMessage());
            }
        }

        foreach ([[0, 1], [1, 0]] as [$uploaderId, $page]) {
            try {
                (new UploadHistory(new AttachmentModel(), []))->paginate($uploaderId, new User(), $page);
                $this->fail('Invalid requests must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Invalid upload history request.', $exception->getMessage());
            }
        }
    }

    public function testAnnouncementUploadSourceUsesIndependentVisibilityQueries(): void
    {
        $model     = new AnnouncementModel();
        $draft     = (int) $model->insert(['title' => 'Draft', 'body' => 'Body', 'status' => 'draft']);
        $published = (int) $model->insert(['title' => 'Published', 'body' => 'Body', 'status' => 'published', 'published_at' => '2026-10-01 12:00:00']);
        $reader    = $this->createStub(User::class);
        $reader->method('can')->willReturnMap([['announcements.access', true], ['announcements.manage', false]]);
        $manager = $this->createStub(User::class);
        $manager->method('can')->willReturnMap([['announcements.access', false], ['announcements.manage', true]]);
        $source       = new AnnouncementUploadSource($model);
        $readerQuery  = $source->visibleResources($reader);
        $managerQuery = $source->visibleResources($manager);
        $this->assertSame([$published], array_map(static fn (array $row): int => (int) $row['id'], $readerQuery->get()->getResultArray()));
        $this->assertSame(2, $managerQuery->countAllResults());
        $this->assertSame(2, $model->countAllResults());
        $this->assertSame('Draft', $model->find($draft)['title']);
        $this->assertSame('Published', $source->describe([$published])[$published]['title']);
    }

    public function testDateAndNumericRangesAreInclusiveAndRejectInvalidBounds(): void
    {
        $model = new class () extends Model {
            protected $allowedFields = ['username', 'active', 'created_at', 'updated_at'];
        };
        $model->setTable(config('Auth')->tables['users']);

        foreach (['2026-10-01 00:00:00', '2026-10-02 23:59:59', '2026-10-03 00:00:00'] as $index => $createdAt) {
            $model->insert(['username' => 'range' . $index, 'active' => 1, 'created_at' => $createdAt, 'updated_at' => $createdAt]);
        }
        $model->insert(['username' => 'before', 'active' => 1, 'created_at' => '2026-09-30 23:59:59', 'updated_at' => '2026-09-30 23:59:59']);
        $model->insert(['username' => 'inactive', 'active' => 0, 'created_at' => '2026-10-02 12:00:00', 'updated_at' => '2026-10-02 12:00:00']);
        $model->insert(['username' => 'high', 'active' => 2, 'created_at' => '2026-10-02 12:00:00', 'updated_at' => '2026-10-02 12:00:00']);
        $query = new ListQuery(['created_from' => '2026-10-01', 'created_to' => '2026-10-02', 'active_from' => '0.5', 'active_to' => '1'], ['id' => 'id'], 'id');
        $query->apply($model, ranges: ['created' => ['field' => 'created_at', 'type' => 'date'], 'active' => ['field' => 'active', 'type' => 'number']]);
        $this->assertSame(['range1', 'range0'], array_column($model->findAll(), 'username'));

        foreach ([['created_from' => '2026-10-01'], ['created_to' => '2026-10-02']] as $parameters) {
            $oneSided = new ListQuery($parameters, ['id' => 'id'], 'id');
            $oneSided->apply($model, ranges: ['created' => ['field' => 'created_at', 'type' => 'date']]);
            $names = array_column($model->findAll(), 'username');
            $this->assertCount(5, $names);
            if (isset($parameters['created_from'])) {
                $this->assertNotContains('before', $names);
                $this->assertContains('range2', $names);
            } else {
                $this->assertNotContains('range2', $names);
                $this->assertContains('before', $names);
            }
        }

        foreach ([['active_from' => '0.5'], ['active_to' => '1']] as $parameters) {
            $oneSided = new ListQuery($parameters, ['id' => 'id'], 'id');
            $oneSided->apply($model, ranges: ['active' => ['field' => 'active', 'type' => 'number']]);
            $names = array_column($model->findAll(), 'username');
            $this->assertCount(5, $names);
            $this->assertNotContains(isset($parameters['active_from']) ? 'inactive' : 'high', $names);
        }
        $invalid = new ListQuery(['created_from' => '2026-02-30', 'created_to' => ['unsafe'], 'active_from' => '1 OR 1=1'], ['id' => 'id'], 'id');
        $this->assertSame(['from' => '', 'to' => ''], $invalid->range('created', 'date'));
        $this->assertSame(['from' => '', 'to' => ''], $invalid->range('active', 'number'));
        $nullByte = new ListQuery(['created_from' => "2026-10-01\0"], ['id' => 'id'], 'id');
        $this->assertSame(['from' => '', 'to' => ''], $nullByte->range('created', 'date'));
        $reversed = new ListQuery(['active_from' => '2', 'active_to' => '1'], ['id' => 'id'], 'id');
        $this->assertSame(['from' => '', 'to' => ''], $reversed->range('active', 'number'));
        $ordered = new ListQuery(['active_from' => '9', 'active_to' => '10'], ['id' => 'id'], 'id');
        $this->assertSame(['from' => '9', 'to' => '10'], $ordered->range('active', 'number'));
        $reversed = new ListQuery(['active_from' => '10', 'active_to' => '9', 'created_from' => '2026-10-03', 'created_to' => '2026-10-01'], ['id' => 'id'], 'id');
        $this->assertSame(['from' => '', 'to' => ''], $reversed->range('active', 'number'));
        $this->assertSame(['from' => '', 'to' => ''], $reversed->range('created', 'date'));

        foreach ([['1.00000000000000000002', '1.00000000000000000001', true], ['-10.01', '-9.01', false], ['-0.00', '0', false], ['0009.1', '10', false]] as [$from, $to, $reversedRange]) {
            $precise = new ListQuery(['number_from' => $from, 'number_to' => $to], ['id' => 'id'], 'id');
            $this->assertSame($reversedRange ? ['from' => '', 'to' => ''] : ['from' => $from, 'to' => $to], $precise->range('number', 'number'));
        }

        foreach ([['unsafe;name', 'date'], ['created', 'unknown']] as [$name, $type]) {
            try {
                $query->range($name, $type);
                $this->fail('Invalid range configuration must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Invalid range configuration.', $exception->getMessage());
            }
        }
    }

    public function testImportRejectsBadConfigurationAndReportsMalformedRows(): void
    {
        foreach ([[], ['title', 'title']] as $columns) {
            $stream = fopen('php://temp', 'w+b');

            try {
                try {
                    (new CsvImport())->import($stream, $columns, static fn (): array => ['result' => 'created', 'reason' => '']);
                    $this->fail('Invalid columns must be rejected.');
                } catch (InvalidArgumentException $exception) {
                    $this->assertSame('Import columns must be nonempty and unique.', $exception->getMessage());
                }
            } finally {
                fclose($stream);
            }
        }
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "title,count\nMissing\nInvalid,1\nValid,2\n");
        rewind($stream);

        try {
            $report = (new CsvImport())->import($stream, ['title', 'count'], static fn (array $data): array => $data['title'] === 'Invalid' ? ['result' => 'unknown'] : ['result' => 'created', 'reason' => '']);
            $this->assertSame(['invalid', 'processing', ''], array_column($report, 'reason'));
            $this->assertSame(['error', 'error', 'created'], array_column($report, 'result'));
        } finally {
            fclose($stream);
        }
    }

    public function testAttachmentLimitsParsePhpQuantitiesAndRejectInvalidLimits(): void
    {
        foreach ([['10M', '12M', 10485760], ['10M', '1M', 1048576], ['1M', '0', 1048576], ['1500K', '2M', 1536000], ['1024', '2048', 1024], ['1G', '2G', 1073741824]] as [$uploadLimit, $postLimit, $expected]) {
            $this->assertSame($expected, Attachments::limitFrom($uploadLimit, $postLimit));
        }

        foreach ([['0', '8M'], ['-1', '8M'], ['1M', '-1']] as [$uploadLimit, $postLimit]) {
            try {
                Attachments::limitFrom($uploadLimit, $postLimit);
                $this->fail('Invalid PHP upload limits must be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Invalid PHP upload limits.', $exception->getMessage());
            }
        }
    }

    public function testAttachmentsRejectUnsupportedTypesAndSizeAndSanitizeNames(): void
    {
        $this->assertSame(Attachments::limitFrom((string) ini_get('upload_max_filesize'), (string) ini_get('post_max_size')), Attachments::maxBytes());
        $text     = "Attachment boundary text.\n";
        $contents = substr(str_repeat($text, intdiv(Attachments::maxBytes() + 1, strlen($text)) + 1), 0, Attachments::maxBytes() + 1);

        foreach ([['script.html', 'small', false], ['large.txt', $contents, false], ['boundary.txt', substr($contents, 0, Attachments::maxBytes()), true], ["../x\"\n.txt", 'content', true], [str_repeat('A', 200) . '.txt', 'content', true]] as [$clientName, $contents, $accepted]) {
            $source = tempnam(sys_get_temp_dir(), 'attachment-policy-');
            file_put_contents($source, $contents);
            $path = null;

            try {
                $upload = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$source, $clientName, 'text/plain', filesize($source), UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid', 'move'])->getMock();
                $this->assertSame('text/plain', $upload->getMimeType());
                $upload->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
                if ($accepted) {
                    $upload->expects($this->once())->method('move')->willReturnCallback(static function (string $target, ?string $name) use ($source, &$path): bool {
                        $path = $target . '/' . $name;

                        return copy($source, $path);
                    });
                } else {
                    $upload->expects($this->never())->method('move');
                }
                $service = new Attachments();

                try {
                    $attachmentId = $service->upload('user', 101, $upload, 1);
                    $this->assertTrue($accepted);
                    $attachment = $service->find('user', 101, $attachmentId);
                    $this->assertDoesNotMatchRegularExpression('/[\\\\\/\r\n"]/', $attachment['original_name']);
                    $this->assertLessThanOrEqual(180, mb_strlen($attachment['original_name']));
                    $this->assertSame(match ($clientName) {
                        'boundary.txt' => 'boundary.txt',
                        "../x\"\n.txt" => 'x__.txt',
                        default        => str_repeat('A', 180),
                    }, $attachment['original_name']);
                    $service->remove('user', 101, $attachmentId);
                } catch (InvalidArgumentException $exception) {
                    $this->assertFalse($accepted);
                    $this->assertSame(0, (new AttachmentModel())->countAllResults());
                }
            } finally {
                unlink($source);
                if ($path !== null && is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testImportMapsValidatesAndContinuesAfterRowFailure(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "title,count,ignored\nValid,2,unsafe\nInvalid,bad,unsafe\nExtra,3,unsafe,unexpected\nThrows,4,unsafe\nSkip,5,unsafe\n");
        rewind($stream);
        $processed = [];

        try {
            $report = (new CsvImport())->import($stream, ['title', 'count', 'ignored'], static function (array $data) use (&$processed): array {
                $processed[] = $data;
                if ($data['title'] === 'Throws') {
                    throw new RuntimeException('Private internal failure.');
                }

                return ['result' => $data['title'] === 'Skip' ? 'skipped' : 'created', 'reason' => $data['title'] === 'Skip' ? 'duplicate' : ''];
            }, 500, ['title' => 'required', 'count' => 'required|integer']);
            $this->assertSame(['created', 'error', 'error', 'error', 'skipped'], array_column($report, 'result'));
            $this->assertSame(['', 'invalid', 'invalid', 'processing', 'duplicate'], array_column($report, 'reason'));
            $this->assertSame([2, 3, 4, 5, 6], array_column($report, 'row'));
            $this->assertArrayHasKey('count', $report[1]['errors']);
            $this->assertSame(['title' => 'Valid', 'count' => '2'], $processed[0]);
            $this->assertCount(3, $processed);
            $this->assertStringNotContainsString('Private internal failure', json_encode($report));
        } finally {
            fclose($stream);
        }
    }

    public function testImportChecksLimitBeforeProcessingAnyRows(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "title\nFirst\nSecond\n");
        rewind($stream);
        $processed = false;

        try {
            try {
                (new CsvImport())->import($stream, ['title'], static function () use (&$processed): array {
                    $processed = true;

                    return ['result' => 'created', 'reason' => ''];
                }, 1);
                $this->fail('Over-limit import must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertFalse($processed);
            }
        } finally {
            fclose($stream);
        }
    }

    public function testAttachmentSaveFailureCleansNewFile(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'attachment-failure-');
        file_put_contents($source, 'attachment content');
        $path   = null;
        $upload = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$source, 'document.txt', 'text/plain', filesize($source), UPLOAD_ERR_OK])
            ->onlyMethods(['isValid', 'move'])->getMock();
        $upload->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
        $upload->expects($this->once())->method('move')->willReturnCallback(static function (string $target, ?string $name) use ($source, &$path): bool {
            $path = $target . '/' . $name;

            return copy($source, $path);
        });
        $model = $this->getMockBuilder(AttachmentModel::class)->onlyMethods(['insert'])->getMock();
        $model->expects($this->once())->method('insert')->willReturn(false);

        try {
            try {
                (new Attachments($model))->upload('user', 101, $upload, 1);
                $this->fail('Failed save must not report successful upload.');
            } catch (RuntimeException $exception) {
                $this->assertNotNull($path);
                $this->assertFileDoesNotExist($path);
                $this->assertSame(0, (new AttachmentModel())->countAllResults());
            }
        } finally {
            unlink($source);
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testAttachmentRemovalCleanupFailureLeavesNoDownloadableRecord(): void
    {
        $directory = WRITEPATH . 'uploads/attachments/';
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $filename = bin2hex(random_bytes(16)) . '.txt';
        file_put_contents($directory . $filename, 'content');
        $model        = new AttachmentModel();
        $attachmentId = (int) $model->insert(['resource_type' => 'user', 'resource_id' => 101, 'filename' => $filename, 'original_name' => 'document.txt', 'mime_type' => 'text/plain', 'size_bytes' => 7, 'uploaded_by' => 1]);
        $storage      = new UploadStorage('attachments', ['text/plain'], ['txt'], 100, static fn (string $file): bool => false);

        try {
            $failedModel = $this->getMockBuilder(AttachmentModel::class)->onlyMethods(['delete'])->getMock();
            $failedModel->expects($this->once())->method('delete')->willReturn(false);

            try {
                (new Attachments($failedModel))->remove('user', 101, $attachmentId);
                $this->fail('Failed database deletion must not report success.');
            } catch (RuntimeException $exception) {
                $this->assertNotNull($model->find($attachmentId));
                $this->assertFileExists($directory . $filename);
            }
            $service = new Attachments($model, $storage);
            $this->assertTrue($service->remove('user', 101, $attachmentId));
            $this->assertNull($service->find('user', 101, $attachmentId));
            $this->assertFileExists($directory . $filename);
            $this->assertLogged('error', 'Attachment file cleanup failed: RuntimeException');
        } finally {
            unlink($directory . $filename);
        }
    }

    public function testAttachmentRoundTripIsScopedToResource(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'attachment-');
        file_put_contents($source, 'attachment content');
        $upload = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$source, 'document.txt', 'text/plain', filesize($source), UPLOAD_ERR_OK])
            ->onlyMethods(['isValid', 'move'])->getMock();
        $upload->expects($this->atLeastOnce())->method('isValid')->willReturn(true);
        $upload->expects($this->once())->method('move')->willReturnCallback(static fn (string $target, ?string $name): bool => copy($source, $target . '/' . $name));
        $service = new Attachments();
        $path    = null;

        try {
            $attachmentId = $service->upload('user', 101, $upload, 1);
            $this->assertNull($service->find('user', 102, $attachmentId));
            $this->assertNull($service->find('other', 101, $attachmentId));
            $attachment = $service->find('user', 101, $attachmentId);
            $path       = $service->path($attachment);
            $this->assertSame('document.txt', $attachment['original_name']);
            $this->assertSame('attachment content', file_get_contents($path));
            $this->assertFalse($service->remove('user', 102, $attachmentId));
            $this->assertTrue($service->remove('user', 101, $attachmentId));
            $this->assertNull($service->find('user', 101, $attachmentId));
            $this->assertFileDoesNotExist($path);
        } finally {
            unlink($source);
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testAttachmentRejectsMimeExtensionMismatchBeforeMoving(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'attachment-mime-');
        file_put_contents($source, 'plain text disguised as an image');

        try {
            $upload = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, 'image.png', 'image/png', filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid', 'move'])->getMock();
            $upload->expects($this->once())->method('isValid')->willReturn(true);
            $upload->expects($this->never())->method('move');
            $this->expectException(InvalidArgumentException::class);
            (new Attachments())->upload('user', 101, $upload, 1);
        } finally {
            unlink($source);
        }
    }

    public function testQueryRejectsUntrustedSortAndArrayParameters(): void
    {
        $query = new ListQuery(['q' => ['unsafe'], 'sort' => 'id; DROP TABLE users', 'direction' => 'ASC NULLS FIRST'], ['id' => 'id', 'username' => 'username'], 'username');
        $this->assertSame('', $query->search);
        $this->assertSame('username', $query->sort);
        $this->assertSame('id', (new ListQuery(['sort' => 'id'], ['id' => 'id', 'username' => 'username'], 'username'))->sort);
        $this->assertSame('DESC', $query->direction);
        $this->assertSame(100, mb_strlen((new ListQuery(['q' => str_repeat('a', 101)], ['id' => 'id'], 'id'))->search));
    }

    public function testQueryCombinesSearchWithAllowlistedFiltersAndStableSorting(): void
    {
        $model = new class () extends Model {
            protected $allowedFields = ['username', 'active'];
            protected $useTimestamps = true;
        };
        $model->setTable(config('Auth')->tables['users']);
        $model->insert(['username' => 'SameFirst', 'active' => 1]);
        $first = $model->getInsertID();
        $model->insert(['username' => 'SameSecond', 'active' => 1]);
        $second = $model->getInsertID();
        $model->insert(['username' => 'SameExcluded', 'active' => 0]);
        $model->insert(['username' => 'Different', 'active' => 1]);

        $query = new ListQuery(['q' => 'sAmE', 'active' => '1', 'sort' => 'active', 'direction' => 'ASC'], ['active' => 'active'], 'active');
        $query->apply($model, ['username'], ['active' => ['field' => 'active', 'values' => ['0', '1']]]);
        $this->assertSame([(int) $second, (int) $first], array_map('intval', array_column($model->findAll(), 'id')));

        $query = new ListQuery(['active' => '1 OR 1=1'], ['id' => 'id'], 'id');
        $query->apply($model, filters: ['active' => ['field' => 'active', 'values' => ['0', '1']]]);
        $this->assertCount(4, $model->findAll());
    }

    public function testCsvEscapesFormulasAndRoundTripsQuotedMultilineValues(): void
    {
        $csv      = new Csv();
        $contents = $csv->write(['title', 'body'], [['=SUM(1,2)', "quoted,\"value\"\nnext line"], ['@command', '-formula'], ['+1', "\tx"], ["\nx", "\rx"]], 4);
        $stream   = fopen('php://temp', 'w+b');
        fwrite($stream, "\xEF\xBB\xBF" . $contents);
        rewind($stream);

        try {
            $rows = $csv->read($stream, ['title', 'body']);
            $this->assertSame(["'=SUM(1,2)", "quoted,\"value\"\nnext line"], $rows[0]['fields']);
            $this->assertSame(["'@command", "'-formula"], $rows[1]['fields']);
            $this->assertSame(["'+1", "'\tx"], $rows[2]['fields']);
            $this->assertSame(["'\nx", "'\rx"], $rows[3]['fields']);
        } finally {
            fclose($stream);
        }
    }

    public function testCsvExportEnforcesLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Csv())->write(['title'], [['first'], ['second']], 1);
    }

    public function testCsvExportRejectsWrongColumnCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Csv())->write(['title'], [['first', 'extra']]);
    }

    public function testStorageRejectsMissingFileAndInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UploadStorage('test', ['text/plain'], ['txt'], 100))->store(null);
    }

    public function testStorageRejectsDirectoryTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UploadStorage('../public', ['text/plain'], ['txt'], 100);
    }

    public function testStorageChecksActualMimeExtensionAndSize(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'upload-policy-');
        file_put_contents($source, 'plain text content');

        try {
            foreach ([['image/png', 'txt', 100], ['text/plain', 'png', 100], ['text/plain', 'txt', 1]] as [$mime, $extension, $limit]) {
                $file = $this->getMockBuilder(UploadedFile::class)
                    ->setConstructorArgs([$source, 'input.txt', 'image/png', 1, UPLOAD_ERR_OK])
                    ->onlyMethods(['isValid'])->getMock();
                $file->expects($this->once())->method('isValid')->willReturn(true);

                try {
                    (new UploadStorage('test', [$mime], [$extension], $limit))->store($file);
                    $this->fail('Invalid upload must be rejected.');
                } catch (InvalidArgumentException $exception) {
                    $this->assertSame('Invalid uploaded file.', $exception->getMessage());
                }
            }
        } finally {
            unlink($source);
        }
    }

    public function testStorageResolvesOnlyControlledFilesAndDeletesThem(): void
    {
        $folder    = 'storage-test-' . bin2hex(random_bytes(8));
        $directory = WRITEPATH . 'uploads/' . $folder;
        mkdir($directory, 0750, true);
        file_put_contents($directory . '/safe.txt', 'content');
        symlink($directory . '/safe.txt', $directory . '/link.txt');
        $storage = new UploadStorage($folder, ['text/plain'], ['txt'], 100);

        try {
            $this->assertSame(realpath($directory . '/safe.txt'), $storage->path('safe.txt'));
            $this->assertNull($storage->path('../safe.txt'));
            $this->assertNull($storage->path('link.txt'));
            $this->assertNull($storage->path('missing.txt'));
            $storage->delete('../safe.txt');
            $this->assertFileExists($directory . '/safe.txt');
            $storage->delete('safe.txt');
            $this->assertFileDoesNotExist($directory . '/safe.txt');
        } finally {
            unlink($directory . '/link.txt');
            if (is_file($directory . '/safe.txt')) {
                unlink($directory . '/safe.txt');
            }
            rmdir($directory);
        }
    }

    public function testStorageCreatesDirectoryAndStoresRandomLowercaseFilename(): void
    {
        $folder    = 'storage-test-' . bin2hex(random_bytes(8));
        $directory = WRITEPATH . 'uploads/' . $folder;
        $source    = tempnam(sys_get_temp_dir(), 'upload-success-');
        file_put_contents($source, 'plain text content');
        $filename = null;

        try {
            $upload = $this->getMockBuilder(UploadedFile::class)
                ->setConstructorArgs([$source, 'input.TXT', 'text/plain', filesize($source), UPLOAD_ERR_OK])
                ->onlyMethods(['isValid', 'move'])->getMock();
            $upload->expects($this->once())->method('isValid')->willReturn(true);
            $upload->expects($this->once())->method('move')->willReturnCallback(static fn (string $target, ?string $name): bool => copy($source, $target . '/' . $name));
            $storage  = new UploadStorage($folder, ['text/plain'], ['txt'], filesize($source));
            $filename = $storage->store($upload);
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{32}\.txt\z/', $filename);
            $this->assertDirectoryExists($directory);
            $this->assertSame('plain text content', file_get_contents($storage->path($filename)));
        } finally {
            unlink($source);
            if ($filename !== null && is_file($directory . '/' . $filename)) {
                unlink($directory . '/' . $filename);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testStorageRejectsInvalidHttpUpload(): void
    {
        $upload = new UploadedFile('/missing-upload', 'input.txt', 'text/plain', 10, UPLOAD_ERR_PARTIAL);
        $this->expectException(InvalidArgumentException::class);
        (new UploadStorage('test', ['text/plain'], ['txt'], 100))->store($upload);
    }
}
