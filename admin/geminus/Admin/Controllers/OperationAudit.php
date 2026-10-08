<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;

class OperationAudit extends BaseController
{
    public function index(): ResponseInterface|string
    {
        if (! auth()->user()?->can('admin.settings')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');
        $actorId  = trim((string) $this->request->getGet('actor'));
        $targetId = trim((string) $this->request->getGet('target'));
        $type     = (string) $this->request->getGet('type');
        $result   = (string) $this->request->getGet('result');
        $from     = (string) $this->request->getGet('from');
        $to       = (string) $this->request->getGet('to');
        $query    = db_connect()->table('operation_audit_logs');

        if ($actorId !== '' && ctype_digit($actorId) && strlen($actorId) <= 10 && (int) $actorId <= 2147483647) {
            $query->where('actor_id', $actorId);
        }
        if ($targetId !== '' && mb_strlen($targetId) <= 32 && preg_match('/^[\pL\pN._-]+$/u', $targetId)) {
            $query->where('target_id', $targetId);
        }
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $type)) {
            $query->where('target_type', $type);
        }
        if (in_array($result, ['success', 'failed', 'redirected'], true)) {
            $query->where('result', $result);
        }

        foreach (['from' => $from, 'to' => $to] as $bound => $date) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                $midnight = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
                $query->where('created_at ' . ($bound === 'from' ? '>=' : '<'), ($bound === 'from' ? $midnight : $midnight->modify('+1 day'))->format('Y-m-d H:i:s'));
            }
        }

        $total = (clone $query)->countAllResults();
        $page  = min(max(1, (int) $this->request->getGet('page')), max(1, (int) ceil($total / 20)));
        $rows  = $query->orderBy('id', 'DESC')->limit(20, ($page - 1) * 20)->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['created_at'] = new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC'));
        }
        unset($row);

        return view('Geminus\Admin\Views\operation_audit', [
            'me'         => auth()->user(),
            'page_title' => lang('Admin.operationAudit'),
            'rows'       => $rows,
            'total'      => $total,
            'pager'      => Services::pager(null, null, false)->makeLinks($page, 20, $total),
            'actorId'    => $actorId,
            'targetId'   => $targetId,
            'type'       => $type,
            'result'     => $result,
            'from'       => $from,
            'to'         => $to,
        ]);
    }
}
