<?php

declare(strict_types=1);

use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Queue\QueuePushResult;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Geminus\Admin\Jobs\SendEmail;
use Geminus\Admin\Libraries\QueuedEmail;

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

        $email = service('email', null, false);
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

        $email = $this->getMockBuilder(QueuedEmail::class)->onlyMethods(['sendDirect', 'printDebugger'])->getMock();
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
        $email = new QueuedEmail();
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
