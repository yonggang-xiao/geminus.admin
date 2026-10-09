<?php

declare(strict_types=1);

namespace Geminus\Admin\Jobs;

use CodeIgniter\Queue\BaseJob;
use Geminus\Admin\Models\EmailDeliveryLogModel;
use RuntimeException;
use Throwable;

class SendEmail extends BaseJob
{
    protected int $tries = 3;

    public function process(): void
    {
        $deliveryLogs = new EmailDeliveryLogModel();
        $auditId      = (int) $this->data['audit_id'];
        $log          = $deliveryLogs->findUnsent($auditId);

        if ($log === null) {
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
        } catch (Throwable $exception) {
            $failureReason ??= isset($email) ? $email->auditFailureReason($exception->getMessage()) : 'Email service unavailable.';
        }

        $deliveryLogs->recordAttempt($auditId, (int) $log['attempts'] + 1, $failureReason ?? null);

        if (isset($exception)) {
            throw $exception;
        }
    }
}
