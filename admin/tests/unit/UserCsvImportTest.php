<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Libraries\UserCsvImport;
use Geminus\Admin\Libraries\UserProvisioning;

/**
 * @internal
 */
final class UserCsvImportTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace;

    public function testProcessingFailureMapsToSaveAndDoesNotStopLaterRows(): void
    {
        $provisioning = $this->getMockBuilder(UserProvisioning::class)->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $provisioning->expects($this->atLeastOnce())->method('create')->willReturnCallback(static function (string $username): string {
            if ($username === 'failed') {
                throw new RuntimeException('Private provisioning failure.');
            }

            return 'created';
        });
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nfailed,failed@example.com\nvalid,valid@example.com\n");
        rewind($stream);

        try {
            $report = (new UserCsvImport($provisioning))->import($stream);
            $this->assertSame(['error', 'created'], array_column($report, 'result'));
            $this->assertSame(['save', ''], array_column($report, 'reason'));
            $this->assertStringNotContainsString('Private provisioning failure', json_encode($report));
        } finally {
            fclose($stream);
        }
    }

    public function testImportSkipsDuplicateEmailsAndReportsEachRow(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nfirst,first@example.com\nother,FIRST@example.com\ninvalid,not-an-email\nsecond,second@example.com\n");
        rewind($stream);

        $report = (new UserCsvImport(service('userProvisioning')))->import($stream);
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

        $report = (new UserCsvImport(service('userProvisioning')))->import($stream);
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
            (new UserCsvImport(service('userProvisioning')))->import($stream);
            $this->fail('Expected an invalid CSV exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(0, auth()->getProvider()->countAllResults());
        } finally {
            fclose($stream);
        }
    }

    public function testExactlyFiveHundredRowsAreAccepted(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\n" . str_repeat("valid,valid@example.com\n", 500));
        rewind($stream);

        $report = (new UserCsvImport(service('userProvisioning')))->import($stream);
        fclose($stream);

        $this->assertCount(500, $report);
        $this->assertSame(501, $report[499]['row']);
        $this->assertSame('created', $report[0]['result']);
        $this->assertSame('skipped', $report[499]['result']);
        $this->assertSame(1, auth()->getProvider()->countAllResults());
    }

    public function testInvalidHeaderDoesNotCreateAccounts(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "email,username\nfirst@example.com,first\n");
        rewind($stream);

        try {
            (new UserCsvImport(service('userProvisioning')))->import($stream);
            $this->fail('Expected an invalid CSV header.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('CSV header must be username,email.', $exception->getMessage());
            $this->assertSame(0, auth()->getProvider()->countAllResults());
        } finally {
            fclose($stream);
        }
    }

    public function testBackslashBeforeClosingQuoteDoesNotConsumeNextRow(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\n\"invalid\\\",invalid@example.com\nvalid,valid@example.com\n");
        rewind($stream);

        try {
            $report = (new UserCsvImport(service('userProvisioning')))->import($stream);

            $this->assertSame([2, 3], array_column($report, 'row'));
            $this->assertSame(['error', 'created'], array_column($report, 'result'));
            $this->assertSame('valid', auth()->getProvider()->findByCredentials(['email' => 'valid@example.com'])->username);
        } finally {
            fclose($stream);
        }
    }

    public function testInvalidColumnsAndExistingAccountsAreReportedWithoutChanges(): void
    {
        $existing        = new AdminUser(['username' => 'existing']);
        $existing->email = 'existing@example.com';
        $existing->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($existing);

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nextra,extra@example.com,unexpected\nother,existing@example.com\nexisting,new@example.com\n");
        rewind($stream);
        $report = (new UserCsvImport(service('userProvisioning')))->import($stream);
        fclose($stream);

        $this->assertSame(['error', 'skipped', 'error'], array_column($report, 'result'));
        $this->assertSame(['invalid', 'duplicate', 'username'], array_column($report, 'reason'));
        $this->assertSame(1, $users->countAllResults());
        $this->assertSame('existing', $users->findByCredentials(['email' => 'existing@example.com'])->username);
        $this->assertNull($users->findByCredentials(['email' => 'new@example.com']));
    }

    public function testReportUsesOriginalCsvRowNumbers(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "\xEF\xBB\xBFusername,email\n\nvalid,valid@example.com\n");
        rewind($stream);

        $report = (new UserCsvImport(service('userProvisioning')))->import($stream);
        fclose($stream);

        $this->assertSame([3], array_column($report, 'row'));
        $this->assertSame(['created'], array_column($report, 'result'));
    }

    public function testImportUsesConfiguredUsernameAndEmailRules(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nname_with_underscore,invalidname@example.com\nvalid.name,valid@example.com\n");
        rewind($stream);

        $report = (new UserCsvImport(service('userProvisioning')))->import($stream);
        fclose($stream);

        $this->assertSame(['error', 'created'], array_column($report, 'result'));
        $this->assertNull(auth()->getProvider()->findByCredentials(['email' => 'invalidname@example.com']));
    }

    public function testImportedUsersHaveUnusablePasswordHash(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "username,email\nfirst,first@example.com\nsecond,second@example.com\n");
        rewind($stream);

        (new UserCsvImport(service('userProvisioning')))->import($stream);
        fclose($stream);

        $first  = auth()->getProvider()->findByCredentials(['email' => 'first@example.com']);
        $second = auth()->getProvider()->findByCredentials(['email' => 'second@example.com']);
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        foreach ([$first, $second] as $user) {
            foreach (['', 'password', 'changeme', $user->username, $user->email] as $password) {
                $this->assertFalse(password_verify($password, $user->getEmailIdentity()->secret2));
                $this->assertFalse(auth()->attempt(['email' => $user->email, 'password' => $password])->isOK());
                $this->assertFalse(auth()->loggedIn());
            }
        }
    }
}
