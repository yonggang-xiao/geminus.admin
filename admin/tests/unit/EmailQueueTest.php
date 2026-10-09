<?php

declare(strict_types=1);

use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Geminus\Admin\Jobs\SendEmail;
use Geminus\Admin\Libraries\QueuedEmail;
use Geminus\Admin\Models\EmailDeliveryLogModel;

/**
 * @internal
 */
final class EmailQueueTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        Services::resetSingle('email');
        Services::resetSingle('queue');
        parent::tearDown();
    }

    public function testEmailIsQueuedAndAuditedWithoutSending(): void
    {
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->once())->method('push')->with('email', 'send-email', $this->callback(static fn (array $data): bool => $data['to'] === ['recipient@example.com'] && $data['subject'] === 'Hello' && $data['body'] === 'Private body'))->willReturn(QueuePushResult::success(42));
        Services::injectMock('queue', $queue);

        $email = $this->getMockBuilder(QueuedEmail::class)->setConstructorArgs([new EmailDeliveryLogModel(), $queue])->onlyMethods(['sendDirect'])->getMock();
        $email->expects($this->never())->method('sendDirect');
        $this->assertInstanceOf(QueuedEmail::class, $email);
        $email->setFrom('sender@example.com')->setTo('recipient@example.com')->setSubject('Hello')->setMessage('Private body');
        $this->assertTrue($email->send());

        $log = db_connect()->table('email_delivery_logs')->get()->getRowArray();
        $this->assertSame('queued', $log['status']);
        $this->assertSame('recipient@example.com', $log['recipient']);
        $this->assertSame('Hello', $log['subject']);
        $this->assertSame(42, (int) $log['job_id']);
        $this->assertSame(0, (int) $log['attempts']);
        $this->assertNull($log['failure_reason']);
        $this->assertArrayNotHasKey('body', $log);
    }

    public function testAuditTimestampsRemainUtcWhenDefaultTimezoneChanges(): void
    {
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->once())->method('push')->willReturn(QueuePushResult::success(42));
        Services::injectMock('queue', $queue);

        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $before = gmdate('Y-m-d H:i:s');
            $email  = service('email', null, false);
            $email->setFrom('sender@example.com')->setTo('recipient@example.com')->setSubject('Hello');
            $this->assertTrue($email->send());
            $log = db_connect()->table('email_delivery_logs')->get()->getRowArray();

            $transport = $this->getMockBuilder(QueuedEmail::class)->setConstructorArgs([new EmailDeliveryLogModel(), $queue])->onlyMethods(['sendDirect'])->getMock();
            $transport->expects($this->once())->method('sendDirect')->willReturn(true);
            Services::injectMock('email', $transport);
            (new SendEmail([
                'audit_id' => $log['id'], 'to' => ['recipient@example.com'], 'cc' => [], 'bcc' => [],
                'subject'  => 'Hello', 'body' => 'Private body', 'type' => 'text',
            ]))->process();
            $after = gmdate('Y-m-d H:i:s');
        } finally {
            date_default_timezone_set($originalTimezone);
        }

        $processed = db_connect()->table('email_delivery_logs')->where('id', $log['id'])->get()->getRowArray();
        $this->assertGreaterThanOrEqual($before, $log['created_at']);
        $this->assertLessThanOrEqual($after, $log['created_at']);
        $this->assertGreaterThanOrEqual($before, $processed['processed_at']);
        $this->assertLessThanOrEqual($after, $processed['processed_at']);
    }

    public function testInvitationAuditContextIsClearedBeforeReusingEmailService(): void
    {
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->exactly(2))->method('push')->willReturnOnConsecutiveCalls(QueuePushResult::success(41), QueuePushResult::success(42));
        Services::injectMock('queue', $queue);

        $email = service('email', null, false);
        $email->setFrom('sender@example.com')->setTo('invitee@example.com')->setSubject('Invite')->setInvitationUserId(9);
        $this->assertTrue($email->send());

        $email->setFrom('sender@example.com')->setTo('someone@example.com')->setSubject('Other');
        $this->assertTrue($email->send());

        $logs = db_connect()->table('email_delivery_logs')->orderBy('id')->get()->getResultArray();
        $this->assertSame(9, (int) $logs[0]['invited_user_id']);
        $this->assertNull($logs[1]['invited_user_id']);
    }

    public function testSuccessfulSendClearsAllQueuedMessageState(): void
    {
        $payloads = [];
        $queue    = $this->createMock(QueueInterface::class);
        $queue->expects($this->exactly(2))->method('push')->willReturnCallback(static function (string $queueName, string $jobName, array $data) use (&$payloads): QueuePushResult {
            $payloads[] = $data;

            return QueuePushResult::success(count($payloads));
        });
        Services::injectMock('queue', $queue);

        $email = service('email', null, false);
        $email->setFrom('sender@example.com')->setTo('first@example.com')->setCC('cc@example.com')->setBCC('bcc@example.com')->setSubject('First')->setMessage('First body')->setInvitationUserId(9);
        $this->assertTrue($email->send());
        $this->assertFalse($email->send());

        $email->setFrom('sender@example.com')->setTo('second@example.com');
        $this->assertTrue($email->send());

        $this->assertSame(['first@example.com'], $payloads[0]['to']);
        $this->assertSame(['cc@example.com'], $payloads[0]['cc']);
        $this->assertSame(['bcc@example.com'], $payloads[0]['bcc']);
        $this->assertSame(['second@example.com'], $payloads[1]['to']);
        $this->assertSame([], $payloads[1]['cc']);
        $this->assertSame([], $payloads[1]['bcc']);
        $this->assertSame('', $payloads[1]['subject']);
        $this->assertSame('', $payloads[1]['body']);
        $logs = db_connect()->table('email_delivery_logs')->orderBy('id')->get()->getResultArray();
        $this->assertSame('', $logs[1]['subject']);
        $this->assertNull($logs[1]['invited_user_id']);
    }

    public function testQueueFailureAndDisabledAutoClearRetainMessageUntilExplicitClear(): void
    {
        $payloads = [];
        $queue    = $this->createMock(QueueInterface::class);
        $queue->expects($this->exactly(4))->method('push')->willReturnCallback(static function (string $queueName, string $jobName, array $data) use (&$payloads): QueuePushResult {
            $payloads[] = $data;

            return count($payloads) === 1 ? QueuePushResult::failure('Unavailable') : QueuePushResult::success(count($payloads));
        });
        Services::injectMock('queue', $queue);

        $email = service('email', null, false);
        $email->setFrom('sender@example.com')->setTo('invitee@example.com')->setCC('cc@example.com')->setBCC('bcc@example.com')->setSubject('Invite')->setMessage('Invite body')->setInvitationUserId(9);
        $this->assertFalse($email->send());
        $this->assertTrue($email->send(false));
        $this->assertTrue($email->send(false));

        foreach ($payloads as $payload) {
            $this->assertSame(['invitee@example.com'], $payload['to']);
            $this->assertSame(['cc@example.com'], $payload['cc']);
            $this->assertSame(['bcc@example.com'], $payload['bcc']);
            $this->assertSame('Invite', $payload['subject']);
            $this->assertSame('Invite body', $payload['body']);
        }
        $logs = db_connect()->table('email_delivery_logs')->orderBy('id')->get()->getResultArray();
        $this->assertSame(['failed', 'queued', 'queued'], array_column($logs, 'status'));
        $this->assertSame([9, 9, 9], array_map(static fn (array $log): int => (int) $log['invited_user_id'], $logs));

        $this->assertSame($email, $email->clear(true));
        $this->assertFalse($email->send());
        $email->setFrom('sender@example.com')->setTo('other@example.com');
        $this->assertTrue($email->send());
        $this->assertSame([], $payloads[3]['cc']);
        $this->assertSame([], $payloads[3]['bcc']);
        $this->assertSame('', $payloads[3]['subject']);
        $this->assertSame('', $payloads[3]['body']);
        $latest = db_connect()->table('email_delivery_logs')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertNull($latest['invited_user_id']);
    }

    public function testLogCreationFailureDoesNotEnqueueEmail(): void
    {
        $logs = $this->getMockBuilder(EmailDeliveryLogModel::class)->onlyMethods(['insert'])->getMock();
        $logs->expects($this->once())->method('insert')->willReturn(false);
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->never())->method('push');

        $email = new QueuedEmail($logs, $queue);
        $email->setFrom('sender@example.com')->setTo('recipient@example.com');

        $this->expectExceptionMessage('Failed to create email delivery log.');
        $email->send();
    }

    public function testDirectSendUsesTransportWithoutQueueOrDeliveryLog(): void
    {
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->never())->method('push');
        $email = $this->getMockBuilder(QueuedEmail::class)->setConstructorArgs([new EmailDeliveryLogModel(), $queue, ['protocol' => 'smtp']])->onlyMethods(['sendWithSmtp'])->getMock();
        $email->expects($this->exactly(2))->method('sendWithSmtp')->willReturn(true);
        $email->setFrom('sender@example.com')->setTo('recipient@example.com')->setSubject('Direct')->setMessage('Body');

        $this->assertTrue($email->sendDirect(false));
        $this->assertTrue($email->sendDirect());
        $this->assertFalse($email->sendDirect());
        $this->assertSame(0, db_connect()->table('email_delivery_logs')->countAllResults());
    }

    public function testWorkerSkipsMissingLog(): void
    {
        $email = $this->createMock(QueuedEmail::class);
        $email->expects($this->never())->method('clear');
        $email->expects($this->never())->method('sendDirect');
        Services::injectMock('email', $email);

        $job = new SendEmail(['audit_id' => 0]);
        $this->assertSame(3, $job->getTries());
        $job->process();
        $this->assertSame(0, db_connect()->table('email_delivery_logs')->countAllResults());
    }

    public function testWorkerRecordsThrownTransportFailureBeforeRetrying(): void
    {
        $logs    = new EmailDeliveryLogModel();
        $auditId = $logs->createQueued('recipient@example.com', 'Hello', 9);
        $data    = [
            'audit_id' => $auditId, 'to' => ['recipient@example.com'], 'cc' => ['cc@example.com'], 'bcc' => ['bcc@example.com'],
            'subject'  => 'Hello', 'body' => 'Private body', 'type' => 'html',
        ];
        $exception = new RuntimeException('SMTP credentials rejected: smtp-secret');
        $email     = $this->getMockBuilder(QueuedEmail::class)->setConstructorArgs([$logs, $this->createStub(QueueInterface::class)])->onlyMethods(['sendDirect'])->getMock();
        $email->expects($this->exactly(2))->method('sendDirect')->willReturnCallback(static function () use (&$exception): bool {
            if ($exception !== null) {
                throw $exception;
            }

            return true;
        });
        Services::injectMock('email', $email);

        try {
            (new SendEmail($data))->process();
            $this->fail('Transport exception must trigger a retry.');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $failed = $logs->find($auditId);
        $this->assertSame('failed', $failed['status']);
        $this->assertSame(1, (int) $failed['attempts']);
        $this->assertSame('Email transport authentication failed.', $failed['failure_reason']);
        $this->assertNotNull($failed['processed_at']);
        $this->assertSame(9, (int) $failed['invited_user_id']);

        $exception = null;
        (new SendEmail($data))->process();
        (new SendEmail($data))->process();
        $sent = $logs->find($auditId);
        $this->assertSame('sent', $sent['status']);
        $this->assertSame(2, (int) $sent['attempts']);
        $this->assertNull($sent['failure_reason']);
        $this->assertSame(9, (int) $sent['invited_user_id']);
    }

    public function testQueueFailureIsRecordedAndInvalidEmailIsRejected(): void
    {
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->once())->method('push')->willReturn(QueuePushResult::failure('Private body escaped as Private\\u0020body'));
        Services::injectMock('queue', $queue);

        $email = service('email', null, false);
        $email->setTo('recipient@example.com');
        $this->assertFalse($email->send());

        $email->setFrom('sender@example.com')->setSubject('Hello')->setMessage('Private body');
        $this->assertFalse($email->send());
        $log = db_connect()->table('email_delivery_logs')->get()->getRowArray();
        $this->assertSame('failed', $log['status']);
        $this->assertNull($log['job_id']);
        $this->assertSame(0, (int) $log['attempts']);
        $this->assertSame('Queue push failed.', $log['failure_reason']);
    }

    public function testWorkerAuditsFailureAndSuccessWithoutDuplicatingSentEmail(): void
    {
        $db = db_connect();
        $db->table('email_delivery_logs')->insert([
            'recipient' => 'recipient@example.com', 'subject' => 'Hello', 'status' => 'queued',
            'attempts'  => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $auditId = $db->insertID();
        $data    = [
            'audit_id' => $auditId, 'to' => ['recipient@example.com'], 'cc' => [], 'bcc' => [],
            'subject'  => 'Hello', 'body' => 'Private body', 'type' => 'text',
        ];

        $email = $this->getMockBuilder(QueuedEmail::class)->setConstructorArgs([new EmailDeliveryLogModel(), $this->createStub(QueueInterface::class)])->onlyMethods(['sendDirect', 'printDebugger'])->getMock();
        $email->expects($this->exactly(2))->method('sendDirect')->willReturnOnConsecutiveCalls(false, true);
        $email->method('printDebugger')->willReturn('The following SMTP error was encountered: 421 closing connection');
        Services::injectMock('email', $email);

        try {
            (new SendEmail($data))->process();
            $this->fail('A failed transport must be retried.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP server rejected message (code 421).', $exception->getMessage());
        }
        $log = $db->table('email_delivery_logs')->where('id', $auditId)->get()->getRowArray();
        $this->assertSame('failed', $log['status']);
        $this->assertSame(1, (int) $log['attempts']);
        $this->assertSame('SMTP server rejected message (code 421).', $log['failure_reason']);

        (new SendEmail($data))->process();
        (new SendEmail($data))->process();
        $log = $db->table('email_delivery_logs')->where('id', $auditId)->get()->getRowArray();
        $this->assertSame('sent', $log['status']);
        $this->assertSame(2, (int) $log['attempts']);
        $this->assertNotNull($log['processed_at']);
        $this->assertNull($log['failure_reason']);
    }

    public function testFailureReasonOnlyStoresSafeCategories(): void
    {
        $email = service('email', null, false);
        $email->setMessage('<p>Private body</p>');
        $email->SMTPUser = 'smtp-user';
        $email->SMTPPass = 'smtp-secret';

        $reason = $email->auditFailureReason('SMTP failed sending ' . base64_encode("\0smtp-user\0smtp-secret") . ' ' . base64_encode('Private body'));

        $this->assertSame('Email delivery failed.', $reason);
        $this->assertSame('Email delivery failed.', $email->auditFailureReason(null));
        $this->assertSame('Email transport data transfer failed.', $email->auditFailureReason('<pre>AUTH: 235 Authentication successful</pre><pre>STARTTLS: 220 Ready to start TLS</pre>Unable to send data: encoded payload<br>'));
        $this->assertSame('SMTP server rejected message (code 550).', $email->auditFailureReason('550 mailbox unavailable'));
        $this->assertSame('SMTP server rejected message (code 421).', $email->auditFailureReason('The following SMTP error was encountered: 421 closing connection'));
        $this->assertSame('Email transport connection refused.', $email->auditFailureReason('SMTP connection refused'));
        $this->assertSame('Invalid recipient address.', $email->auditFailureReason('Invalid email address: "user500@example.com"'));
        $this->assertSame('Invalid recipient address.', $email->auditFailureReason('Invalid email address: "ssl@example.com"'));
        $this->assertSame('Email delivery failed.', $email->auditFailureReason('Unable to open a socket to Sendmail. Please check settings.'));
    }

    public function testQueueExceptionRecordsFailureReason(): void
    {
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->once())->method('push')->willThrowException(new RuntimeException('Queue connection refused'));
        Services::injectMock('queue', $queue);

        $email = service('email', null, false);
        $email->setFrom('sender@example.com')->setTo('recipient@example.com')->setMessage('Private body');

        $this->expectExceptionMessage('Queue connection refused');

        try {
            $email->send();
        } finally {
            $log = db_connect()->table('email_delivery_logs')->get()->getRowArray();
            $this->assertSame('failed', $log['status']);
            $this->assertSame('Queue push failed.', $log['failure_reason']);
        }
    }

    public function testQueuePushDoesNotOverwriteWorkerFailure(): void
    {
        $queue = $this->createMock(QueueInterface::class);
        $queue->expects($this->once())->method('push')->willReturnCallback(static function (string $queueName, string $jobName, array $data): QueuePushResult {
            db_connect()->table('email_delivery_logs')->where('id', $data['audit_id'])->update([
                'status' => 'failed', 'failure_reason' => 'SMTP server rejected message (code 550).',
            ]);

            return QueuePushResult::success(43);
        });
        Services::injectMock('queue', $queue);

        $email = service('email', null, false);
        $email->setFrom('sender@example.com')->setTo('recipient@example.com')->setMessage('Private body');
        $this->assertTrue($email->send());

        $log = db_connect()->table('email_delivery_logs')->get()->getRowArray();
        $this->assertSame('failed', $log['status']);
        $this->assertSame('SMTP server rejected message (code 550).', $log['failure_reason']);
        $this->assertSame(43, (int) $log['job_id']);
    }
}
