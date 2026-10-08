<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class OperationAudit implements FilterInterface
{
    private array $flashBefore = [];

    public function before(RequestInterface $request, $arguments = null)
    {
        if ($this->isAdminWrite($request)) {
            $this->flashBefore = session()->getFlashdata();
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $path   = ltrim($request->getUri()->getPath(), '/');
        $method = strtoupper($request->getMethod());

        if (! $this->isAdminWrite($request)) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $status = $response->getStatusCode();
        $result = $status >= 400 ? 'failed' : ($status >= 300 ? $this->redirectResult() : 'success');
        preg_match('~^[^/]+/admin/(.+)$~', $path, $matches);
        $segments   = explode('/', $matches[1]);
        $targetId   = null;
        $targetType = $segments[0];

        foreach ($segments as $index => $segment) {
            if (ctype_digit($segment)) {
                $targetId   = $segment;
                $targetType = $segments[$index - 1] ?? $targetType;
                break;
            }
        }

        if ($targetId === null
            && preg_match('~^(?:settings/(roles|permissions)|mail/(templates))/([^/]+)~', $matches[1], $target)) {
            $targetType = $target[1] ?: $target[2];
            $targetId   = $target[3];
        }

        try {
            db_connect()->table('operation_audit_logs')->insert([
                'actor_id'    => $user->id,
                'action'      => $method,
                'target_type' => mb_substr($targetType, 0, 64),
                'target_id'   => $targetId === null ? null : mb_substr($targetId, 0, 32),
                'path'        => mb_substr($path, 0, 512),
                'result'      => $result,
                'ip_address'  => $request->getIPAddress(),
                'user_agent'  => mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', mb_scrub($request->getHeaderLine('User-Agent'), 'UTF-8')), 0, 512) ?: null,
                'created_at'  => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Operation audit failed: {exception}', ['exception' => $exception]);
        }
    }

    private function isAdminWrite(RequestInterface $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && (bool) preg_match('~^[^/]+/admin/.+$~', ltrim($request->getUri()->getPath(), '/'));
    }

    private function redirectResult(): string
    {
        $success = false;

        foreach (session()->getFlashdata() as $key => $value) {
            if (array_key_exists($key, $this->flashBefore) && $this->flashBefore[$key] === $value) {
                continue;
            }

            if ($key === 'alert' && is_array($value)) {
                if (($value['type'] ?? null) === 'danger') {
                    return 'failed';
                }

                $success = ($value['type'] ?? null) === 'success';
            }

            if ($value !== null && ($key === 'error' || $key === 'errors' || str_ends_with($key, '_errors'))) {
                return 'failed';
            }
        }

        return $success ? 'success' : 'redirected';
    }
}
