<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\Config\OperationAudit as AuditConfig;
use Geminus\Admin\Libraries\AuditSubmission;
use stdClass;
use Throwable;

class OperationAudit implements FilterInterface
{
    private array $flashBefore      = [];
    private array $flashStateBefore = [];
    private array $submission       = [];

    public function before(RequestInterface $request, $arguments = null)
    {
        $this->submission = [];
        if ($this->isAdminWrite($request)) {
            $this->flashBefore      = session()->getFlashdata();
            $this->flashStateBefore = session()->get('__ci_vars') ?? [];

            try {
                $collector  = new AuditSubmission(config(AuditConfig::class)->operations);
                $router     = service('router');
                $controller = $router->controllerName();
                $definition = is_string($controller) ? $collector->match($controller, $router->methodName(), $request->getMethod()) : null;
                if ($definition !== null) {
                    $input = strtoupper($request->getMethod()) === 'POST' ? $request->getPost() : $request->getRawInput();
                    if (preg_match('~\Aapplication/(?:[a-z0-9.-]+\+)?json(?:\s*;|\z)~i', $request->getHeaderLine('Content-Type'))) {
                        try {
                            $input = $request->getJSON(false, 512, JSON_THROW_ON_ERROR);
                            $input = $input instanceof stdClass ? get_object_vars($input) : null;
                        } catch (Throwable) {
                            $input = null;
                        }
                    }
                    if ($input !== null) {
                        foreach ($definition['fields'] as $field) {
                            if (! str_contains($field, '.')) {
                                continue;
                            }
                            unset($input[$field]);
                            [$fileField, $attribute] = explode('.', $field, 2);
                            if (strtoupper($request->getMethod()) !== 'POST'
                                || ! preg_match('~\Amultipart/form-data(?:\s*;|\z)~i', $request->getHeaderLine('Content-Type'))) {
                                continue;
                            }
                            $file = $request->getFile($fileField);
                            if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
                                continue;
                            }
                            $input[$field] = match ($attribute) {
                                'name' => basename(str_replace('\\', '/', $file->getClientName())),
                                'size' => $file->getSize(),
                                'type' => $file->getClientMimeType(),
                            };
                        }
                    }
                    $this->submission = $collector->capture($definition, $input, $router->params());
                }
            } catch (Throwable) {
                log_message('error', 'Operation audit submission capture failed.');
            }
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
            db_connect()->table('operation_audit_logs')->insert(array_replace([
                'actor_id'    => $user->id,
                'action'      => $method,
                'target_type' => mb_substr($targetType, 0, 64),
                'target_id'   => $targetId === null ? null : mb_substr($targetId, 0, 32),
                'path'        => mb_substr($path, 0, 512),
                'result'      => $result,
                'ip_address'  => $request->getIPAddress(),
                'user_agent'  => mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', mb_scrub($request->getHeaderLine('User-Agent'), 'UTF-8')), 0, 512) ?: null,
                'created_at'  => gmdate('Y-m-d H:i:s'),
            ], $this->submission));
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
        $success    = false;
        $flashState = session()->get('__ci_vars') ?? [];

        foreach (session()->getFlashdata() as $key => $value) {
            if (array_key_exists($key, $this->flashBefore) && $this->flashBefore[$key] === $value
                && ($this->flashStateBefore[$key] ?? null) === ($flashState[$key] ?? null)) {
                continue;
            }

            if ($key === '_operation_audit_result') {
                if ($value === 'failed') {
                    return 'failed';
                }

                $success = $success || $value === 'success';
            }

            if ($key === 'alert' && is_array($value)) {
                if (($value['type'] ?? null) === 'danger') {
                    return 'failed';
                }

                $success = $success || ($value['type'] ?? null) === 'success';
            }

            if ($value !== null && ($key === 'error' || $key === 'errors' || str_ends_with($key, '_errors'))) {
                return 'failed';
            }
        }

        return $success ? 'success' : 'redirected';
    }
}
