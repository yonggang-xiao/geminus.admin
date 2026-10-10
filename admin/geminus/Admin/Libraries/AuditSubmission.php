<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use InvalidArgumentException;

class AuditSubmission
{
    private array $operations = [];

    public function __construct(array $definitions)
    {
        if (! array_is_list($definitions)) {
            throw new InvalidArgumentException('Audit operations must be a list.');
        }

        foreach ($definitions as $definition) {
            if (! is_array($definition)
                || array_diff(array_keys($definition), ['controller', 'methods', 'action', 'object', 'fields']) !== []
                || ! is_string($definition['controller'] ?? null)
                || ! preg_match('/\A[A-Za-z_][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*\z/', $definition['controller'])
                || ! is_array($definition['methods'] ?? null) || ! array_is_list($definition['methods']) || $definition['methods'] === []
                || ! is_string($definition['action'] ?? null)
                || ! preg_match('/\A[a-z][a-z0-9_.-]{0,127}\z/', $definition['action'])
                || ! is_array($definition['object'] ?? null)
                || array_diff(array_keys($definition['object']), ['type', 'route_parameter']) !== []
                || ! is_string($definition['object']['type'] ?? null)
                || ! preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $definition['object']['type'])
                || ! is_array($definition['fields'] ?? null) || ! array_is_list($definition['fields']) || $definition['fields'] === []) {
                throw new InvalidArgumentException('Invalid audit operation definition.');
            }

            if (array_key_exists('route_parameter', $definition['object'])
                && (! is_int($definition['object']['route_parameter']) || $definition['object']['route_parameter'] < 0)) {
                throw new InvalidArgumentException('Invalid audit route parameter.');
            }

            foreach ($definition['fields'] as $field) {
                if (! is_string($field) || strlen($field) > 64 || ! preg_match('/\A[A-Za-z_][A-Za-z0-9_-]*(?:\.(?:name|size|type))?\z/', $field)) {
                    throw new InvalidArgumentException('Invalid audit field name.');
                }
            }
            if (count(array_unique($definition['fields'])) !== count($definition['fields'])) {
                throw new InvalidArgumentException('Duplicate audit field name.');
            }

            foreach ($definition['methods'] as $method) {
                if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    throw new InvalidArgumentException('Invalid audit HTTP method.');
                }
                $key = strtolower(ltrim($definition['controller'], '\\')) . ':' . $method;
                if (isset($this->operations[$key])) {
                    throw new InvalidArgumentException('Duplicate audit operation.');
                }
                $this->operations[$key] = $definition;
            }
        }
    }

    public function match(string $controller, string $method, string $httpMethod): ?array
    {
        return $this->operations[strtolower(ltrim($controller, '\\') . '::' . $method) . ':' . strtoupper($httpMethod)] ?? null;
    }

    public function capture(array $definition, ?array $input, array $routeParameters): array
    {
        $summary = ['fields' => [], 'omitted' => [], 'truncated' => []];
        if ($input === null) {
            $summary['input'] = 'invalid_json';
        } else {
            foreach (array_slice($definition['fields'], 0, 32) as $field) {
                if (preg_match('/password|passwd|pwd|smtp.?pass|token|secret|csrf|credential|authorization|cookie|session|api.?key|private.?key|client.?key|access.?key|auth.?code|otp|verification.?code|passcode|(^|_)(code|key|pass)($|_)/i', $field)
                    || ! array_key_exists($field, $input)) {
                    continue;
                }
                $value = $input[$field];
                if (is_float($value) && ! is_finite($value)) {
                    $summary['omitted'][$field] = 'invalid_value';

                    continue;
                }
                if ($value !== null && ! is_scalar($value)) {
                    $summary['omitted'][$field] = 'complex_value';

                    continue;
                }
                if (is_string($value)) {
                    $value = str_replace("\0", '', mb_scrub($value, 'UTF-8'));
                    if (mb_strlen($value) > 256) {
                        $value                  = mb_substr($value, 0, 256);
                        $summary['truncated'][] = $field;
                    }
                }
                $summary['fields'][$field] = $value;
                if (strlen(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > 7168) {
                    unset($summary['fields'][$field]);
                    $summary['truncated']       = array_values(array_diff($summary['truncated'], [$field]));
                    $summary['omitted'][$field] = 'size_limit';
                    $summary['limited']         = true;
                    break;
                }
            }
            if (count($definition['fields']) > 32) {
                $summary['limited'] = true;
            }
        }
        $parameter = $definition['object']['route_parameter'] ?? null;
        $targetId  = $parameter === null ? null : ($routeParameters[$parameter] ?? null);

        return [
            'operation'   => $definition['action'],
            'target_type' => $definition['object']['type'],
            'target_id'   => is_scalar($targetId) ? mb_substr(str_replace("\0", '', mb_scrub((string) $targetId, 'UTF-8')), 0, 32) : null,
            'submission'  => json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }
}
