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
        if (! auth()->user()?->can('operation-audit.view')) {
            return $this->response->setStatusCode(403);
        }

        $this->response->setHeader('Cache-Control', 'private, no-store');
        $actorId  = trim((string) $this->request->getGet('actor'));
        $targetId = trim((string) $this->request->getGet('target'));
        $type     = (string) $this->request->getGet('type');
        $object   = trim((string) $this->request->getGet('object'));
        if (preg_match('/^([a-z][a-z0-9_-]{0,63})\|([\pL\pN._-]{0,32})$/u', $object, $selected)) {
            $type     = $selected[1];
            $targetId = $selected[2];
        }
        $result    = (string) $this->request->getGet('result');
        $from      = (string) $this->request->getGet('from');
        $to        = (string) $this->request->getGet('to');
        $sort      = (string) $this->request->getGet('sort');
        $sort      = in_array($sort, ['created_at', 'actor', 'action', 'object', 'path', 'result', 'ip_address', 'user_agent'], true) ? $sort : 'created_at';
        $direction = strtoupper((string) $this->request->getGet('direction')) === 'ASC' ? 'ASC' : 'DESC';
        $query     = db_connect()->table('operation_audit_logs AS logs')->select('logs.*');

        if ($sort === 'actor') {
            $query->join('users AS actor', 'actor.id = logs.actor_id', 'left');
        }

        if ($actorId !== '' && ctype_digit($actorId) && strlen($actorId) <= 10 && (int) $actorId <= 2147483647) {
            $query->where('logs.actor_id', $actorId);
        }
        if ($targetId !== '' && mb_strlen($targetId) <= 32 && preg_match('/^[\pL\pN._-]+$/u', $targetId)) {
            $query->where('logs.target_id', $targetId);
        }
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $type)) {
            $query->where('logs.target_type', $type);
        }
        if (in_array($result, ['success', 'failed', 'redirected'], true)) {
            $query->where('logs.result', $result);
        }

        foreach (['from' => $from, 'to' => $to] as $bound => $date) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                $midnight = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
                $query->where('logs.created_at ' . ($bound === 'from' ? '>=' : '<'), ($bound === 'from' ? $midnight : $midnight->modify('+1 day'))->format('Y-m-d H:i:s'));
            }
        }

        $total = (clone $query)->countAllResults();
        $page  = min(max(1, (int) $this->request->getGet('page')), max(1, (int) ceil($total / 20)));
        if ($sort === 'object') {
            $query->join('users AS target_user', "CAST(target_user.id AS TEXT) = logs.target_id AND logs.target_type = 'users'", 'left', false)
                ->orderBy('logs.target_type', $direction)
                ->orderBy('COALESCE(target_user.username::text, logs.target_id)', $direction, false);
        } elseif ($sort === 'action') {
            $query->orderBy('COALESCE(logs.operation, logs.action)', $direction, false);
        } else {
            $query->orderBy($sort === 'actor' ? 'actor.username' : 'logs.' . $sort, $direction);
        }
        $rows = $query->orderBy('logs.id', 'DESC')->limit(20, ($page - 1) * 20)->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['created_at']         = new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC'));
            $row['submission_display'] = null;
            if ($row['submission'] !== null) {
                $summary                   = json_decode($row['submission'], true, flags: JSON_THROW_ON_ERROR);
                $row['submission_display'] = ['fields' => [], 'notes' => []];
                if (! is_array($summary) || ! is_array($summary['fields'] ?? null)
                                         || ! is_array($summary['omitted'] ?? null) || ! is_array($summary['truncated'] ?? null)) {
                    $row['submission_display']['notes'][] = lang('Admin.auditInvalidInput');

                    continue;
                }

                foreach ($summary['fields'] ?? [] as $field => $value) {
                    $row['submission_display']['fields'][$field] = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
                }

                foreach ($summary['omitted'] ?? [] as $field => $reason) {
                    $row['submission_display']['notes'][] = $field . ': ' . lang('Admin.auditOmitted');
                }

                foreach ($summary['truncated'] ?? [] as $field) {
                    $row['submission_display']['notes'][] = $field . ': ' . lang('Admin.auditTruncated');
                }
                if (isset($summary['input'])) {
                    $row['submission_display']['notes'][] = lang('Admin.auditInvalidInput');
                }
                if ($summary['limited'] ?? false) {
                    $row['submission_display']['notes'][] = lang('Admin.auditLimited');
                }
            }
        }
        unset($row);

        $actors  = db_connect()->table('operation_audit_logs')->distinct()->select('actor_id')->where('actor_id IS NOT NULL', null, false)->orderBy('actor_id')->get()->getResultArray();
        $objects = db_connect()->table('operation_audit_logs')->distinct()->select('target_type, target_id')->orderBy('target_type')->orderBy('target_id')->get()->getResultArray();
        $userIds = array_column($actors, 'actor_id');

        foreach ($objects as $item) {
            if ($item['target_type'] === 'users' && $item['target_id'] !== null && ctype_digit($item['target_id'])
                && strlen($item['target_id']) <= 10 && (int) $item['target_id'] <= 2147483647) {
                $userIds[] = $item['target_id'];
            }
        }

        $userNames = [];
        if ($userIds !== []) {
            foreach (db_connect()->table('users')->select('id, username')->whereIn('id', array_unique($userIds))->get()->getResultArray() as $user) {
                $userNames[$user['id']] = $user['username'];
            }
        }

        $actorOptions = ['' => lang('Admin.auditAllActors')];

        foreach ($actors as $actor) {
            $actorOptions[$actor['actor_id']] = $userNames[$actor['actor_id']] ?? lang('Admin.auditDeletedUser') . ' #' . $actor['actor_id'];
        }

        $objectOptions = ['' => lang('Admin.auditAllObjects')];

        foreach ($objects as $item) {
            $objectOptions[$item['target_type'] . '|'] = $item['target_type'];
            if ($item['target_id'] !== null) {
                $objectOptions[$item['target_type'] . '|' . $item['target_id']] = $item['target_type'] . " \u{00B7} " . ($item['target_type'] === 'users' ? ($userNames[$item['target_id']] ?? lang('Admin.auditDeletedUser') . ' #' . $item['target_id']) : $item['target_id']);
            }
        }

        return view('Geminus\Admin\Views\operation_audit', [
            'me'            => auth()->user(),
            'page_title'    => lang('Admin.operationAudit'),
            'rows'          => $rows,
            'total'         => $total,
            'currentPage'   => $page,
            'perPage'       => 20,
            'pager'         => Services::pager(null, null, false)->makeLinks($page, 20, $total),
            'actorId'       => $actorId,
            'targetId'      => $targetId,
            'type'          => $type,
            'object'        => $object !== '' ? $object : ($type !== '' ? $type . '|' . $targetId : ''),
            'actorOptions'  => $actorOptions,
            'objectOptions' => $objectOptions,
            'userNames'     => $userNames,
            'result'        => $result,
            'from'          => $from,
            'to'            => $to,
            'sort'          => $sort,
            'direction'     => $direction,
        ]);
    }
}
