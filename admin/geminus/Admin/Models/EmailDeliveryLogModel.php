<?php

declare(strict_types=1);

namespace Geminus\Admin\Models;

use CodeIgniter\Model;
use RuntimeException;

class EmailDeliveryLogModel extends Model
{
    protected $table         = 'email_delivery_logs';
    protected $returnType    = 'array';
    protected $allowedFields = ['recipient', 'subject', 'invited_user_id', 'status', 'attempts', 'created_at', 'job_id', 'failure_reason', 'processed_at'];

    public function dashboardStatusCount(string $status): int
    {
        return $this->where('status', $status)->countAllResults();
    }

    public function createQueued(string $recipient, string $subject, ?int $invitedUserId): int
    {
        $auditId = $this->insert([
            'recipient'       => $recipient,
            'subject'         => $subject,
            'invited_user_id' => $invitedUserId,
            'status'          => 'queued',
            'attempts'        => 0,
            'created_at'      => gmdate('Y-m-d H:i:s'),
        ]);

        if ($auditId === false) {
            throw new RuntimeException('Failed to create email delivery log.');
        }

        return (int) $auditId;
    }

    public function assignJob(int $auditId, ?int $jobId): bool
    {
        return $this->update($auditId, ['job_id' => $jobId]);
    }

    public function markQueueFailed(int $auditId): bool
    {
        return $this->update($auditId, [
            'status'         => 'failed',
            'failure_reason' => 'Queue push failed.',
        ]);
    }

    public function findUnsent(int $auditId): ?array
    {
        return $this->where('status !=', 'sent')->find($auditId);
    }

    public function recordAttempt(int $auditId, int $attempts, ?string $failureReason): bool
    {
        return $this->update($auditId, [
            'status'         => $failureReason === null ? 'sent' : 'failed',
            'attempts'       => $attempts,
            'processed_at'   => gmdate('Y-m-d H:i:s'),
            'failure_reason' => $failureReason,
        ]);
    }
}
