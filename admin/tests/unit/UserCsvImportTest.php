<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Geminus\Admin\Libraries\UserCsvImport;

/**
 * @internal
 */
final class UserCsvImportTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace;

    public function testImportSkipsDuplicateEmailsAndReportsEachRow(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nfirst,first@example.com\nother,FIRST@example.com\ninvalid,not-an-email\nsecond,second@example.com\n");
        rewind($stream);

        $report = (new UserCsvImport())->import($stream);
        fclose($stream);

        $this->assertSame([2, 3, 4, 5], array_column($report, 'row'));
        $this->assertSame(['created', 'skipped', 'error', 'created'], array_column($report, 'result'));
        $this->assertSame('duplicate', $report[1]['reason']);
        $this->assertSame('first', auth()->getProvider()->findByCredentials(['email' => 'first@example.com'])->username);
        $this->assertSame(2, auth()->getProvider()->countAllResults());
    }

    public function testFailedRowDoesNotReserveEmailForLaterValidRow(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nx,shared@example.com\nvalid,shared@example.com\n");
        rewind($stream);

        $report = (new UserCsvImport())->import($stream);
        fclose($stream);

        $this->assertSame(['error', 'created'], array_column($report, 'result'));
        $this->assertSame('valid', auth()->getProvider()->findByCredentials(['email' => 'shared@example.com'])->username);
    }

    public function testTooManyRowsDoNotCreatePartialAccounts(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\n" . str_repeat("valid,valid@example.com\n", 501));
        rewind($stream);

        try {
            (new UserCsvImport())->import($stream);
            $this->fail('Expected an invalid CSV exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(0, auth()->getProvider()->countAllResults());
        } finally {
            fclose($stream);
        }
    }

    public function testReportUsesOriginalCsvRowNumbers(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "\xEF\xBB\xBFusername,email\n\nvalid,valid@example.com\n");
        rewind($stream);

        $report = (new UserCsvImport())->import($stream);
        fclose($stream);

        $this->assertSame([3], array_column($report, 'row'));
        $this->assertSame(['created'], array_column($report, 'result'));
    }

    public function testImportUsesConfiguredUsernameAndEmailRules(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nname_with_underscore,invalidname@example.com\nvalid.name,valid@example.com\n");
        rewind($stream);

        $report = (new UserCsvImport())->import($stream);
        fclose($stream);

        $this->assertSame(['error', 'created'], array_column($report, 'result'));
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'invalidname@example.com']));
    }

    public function testImportedUsersHaveUnusablePasswordHash(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nfirst,first@example.com\nsecond,second@example.com\n");
        rewind($stream);

        (new UserCsvImport())->import($stream);
        fclose($stream);

        $first  = auth()->getProvider()->findByCredentials(['email' => 'first@example.com']);
        $second = auth()->getProvider()->findByCredentials(['email' => 'second@example.com']);
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->getEmailIdentity()->secret2, $second->getEmailIdentity()->secret2);
        $this->assertFalse(password_verify('', $first->getEmailIdentity()->secret2));
    }
}
