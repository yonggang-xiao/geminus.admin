<?php

declare(strict_types=1);

namespace Geminus\Admin\Jobs;

use CodeIgniter\Queue\BaseJob;
use RuntimeException;
use Throwable;

class SendEmail extends BaseJob
{
    protected int $tries = 3;

    public function process(): void
    {
        $db    = db_connect();
        $table = $db->table('email_delivery_logs');
        $log   = $table->where('id', $this->data['audit_id'])->get()->getRowArray();

        if ($log === null || $log['status'] === 'sent') {
            return;
        }

        try {
            $email = service('email');
            $email->clear();
            $email->mailType = $this->data['type'];
            $email->setTo($this->data['to']);
            if ($this->data['cc'] !== []) {
                $email->setCC($this->data['cc']);
            }
            if ($this->data['bcc'] !== []) {
                $email->setBCC($this->data['bcc']);
            }
            $email->setSubject($this->data['subject']);
            $email->setMessage($this->data['body']);

            if (! $email->sendDirect()) {
                $failureReason = $email->auditFailureReason($email->printDebugger([]));

                throw new RuntimeException($failureReason);
            }

            $status = 'sent';
        } catch (Throwable $exception) {
            $status = 'failed';
            $failureReason ??= isset($email) ? $email->auditFailureReason($exception->getMessage()) : 'Email service unavailable.';
        }

        $db->table('email_delivery_logs')->where('id', $this->data['audit_id'])->update([
            'status'         => $status,
            'attempts'       => $log['attempts'] + 1,
            'processed_at'   => gmdate('Y-m-d H:i:s'),
            'failure_reason' => $failureReason ?? null,
        ]);

        if (isset($exception)) {
            throw $exception;
        }
    }
}
