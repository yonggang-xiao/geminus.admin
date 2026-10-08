<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use CodeIgniter\Email\Email;
use RuntimeException;
use Throwable;

class QueuedEmail extends Email
{
    public function send($autoClear = true)
    {
        if (($this->fromEmail === '' && ! isset($this->headers['From']))
            || ($this->recipients === [] && $this->CCArray === [] && $this->BCCArray === []
                && ! isset($this->headers['Cc'], $this->headers['Bcc']))) {
            return false;
        }

        if ($this->attachments !== []) {
            throw new RuntimeException('Queued email attachments are not supported.');
        }

        $db      = db_connect();
        $auditId = $db->table('email_delivery_logs')->insert([
            'recipient'  => implode(', ', $this->recipients),
            'subject'    => $this->tmpArchive['subject'] ?? '',
            'status'     => 'queued',
            'attempts'   => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]) ? $db->insertID() : throw new RuntimeException('Failed to create email delivery log.');

        try {
            $result = service('queue')->push('email', 'send-email', [
                'audit_id' => $auditId,
                'to'       => $this->recipients,
                'cc'       => $this->tmpArchive['CCArray'] ?? $this->CCArray,
                'bcc'      => $this->tmpArchive['BCCArray'] ?? $this->BCCArray,
                'subject'  => $this->tmpArchive['subject'] ?? '',
                'body'     => $this->body,
                'type'     => $this->mailType,
            ]);
        } catch (Throwable $exception) {
            $db->table('email_delivery_logs')->where('id', $auditId)->update([
                'status'         => 'failed',
                'failure_reason' => 'Queue push failed.',
            ]);

            throw $exception;
        }

        $db->table('email_delivery_logs')->where('id', $auditId)->update($result->getStatus()
            ? ['job_id' => $result->getJobId()]
            : ['status' => 'failed', 'failure_reason' => 'Queue push failed.']);

        if ($result->getStatus() && $autoClear) {
            $this->clear();
        }

        return $result->getStatus();
    }

    public function sendDirect($autoClear = true): bool
    {
        return parent::send($autoClear);
    }

    public function auditFailureReason(?string $reason): string
    {
        $reason = preg_replace('/<pre>.*?<\/pre>/s', '', $reason ?? '');

        if (str_contains($reason, 'Invalid email address:')) {
            return 'Invalid recipient address.';
        }

        if (str_contains($reason, 'Unable to send data:')) {
            return 'Email transport data transfer failed.';
        }

        if (preg_match('/(?:^|The following SMTP error was encountered:\s*)([45]\d\d)(?:[ .-]|$)/i', $reason, $matches)) {
            return 'SMTP server rejected message (code ' . $matches[1] . ').';
        }

        if (preg_match('/\bconnection refused\b/i', $reason)) {
            return 'Email transport connection refused.';
        }

        if (preg_match('/\b(?:connection failed|connection timed out|timed out|timeout)\b/i', $reason)) {
            return 'Email transport connection failed or timed out.';
        }

        if (preg_match('/\b(?:authenticat\w*|credentials)\b/i', $reason)) {
            return 'Email transport authentication failed.';
        }

        if (preg_match('/\b(?:TLS|SSL|certificate)\b/i', $reason)) {
            return 'Email transport TLS negotiation failed.';
        }

        return 'Email delivery failed.';
    }
}
