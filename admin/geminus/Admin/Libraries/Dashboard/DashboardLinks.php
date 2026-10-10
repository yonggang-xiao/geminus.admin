<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\Dashboard;

use CodeIgniter\HTTP\URI;
use CodeIgniter\Router\Exceptions\RouterException;
use CodeIgniter\Router\RouteCollection;
use InvalidArgumentException;

class DashboardLinks
{
    public function __construct(private readonly RouteCollection $routes)
    {
    }

    public function resolve(array $link, string $locale, bool $more = false): array
    {
        DashboardItems::fields($link, $more ? ['route', 'label'] : ['route'], ['arguments', 'query']);
        $name = $link['route'];
        if (! is_string($name) || ! str_starts_with($name, 'admin/')) {
            throw new InvalidArgumentException('Dashboard links require a named admin GET route.');
        }
        if ($more) {
            DashboardItems::languageKey($link['label']);
        }

        $arguments = $link['arguments'] ?? [];
        $query     = $link['query'] ?? [];
        if (! is_array($arguments) || ! array_is_list($arguments) || ! is_array($query)) {
            throw new InvalidArgumentException('Invalid dashboard link parameters.');
        }

        foreach ($arguments as $argument) {
            if (! is_int($argument) && ! is_string($argument)) {
                throw new InvalidArgumentException('Dashboard route arguments must be integers or strings.');
            }
        }

        foreach ($query as $key => $value) {
            if (! is_string($key) || ! is_scalar($value) || (is_float($value) && ! is_finite($value))) {
                throw new InvalidArgumentException('Dashboard query parameters must be named scalars.');
            }
        }

        $this->routes->getRoutes('GET', false);
        $pattern = null;

        foreach ($this->routes->getRoutesOptions(null, 'GET') as $route => $options) {
            if (($options['as'] ?? null) === $name && preg_match('#^\{locale\}/admin(?:/|$)#', $route) === 1) {
                $pattern = $route;
                break;
            }
        }
        if ($pattern === null || preg_match_all('/\(([^)]+)\)/', $pattern) !== count($arguments)) {
            throw new InvalidArgumentException('Unknown dashboard GET route or incorrect argument count: ' . $name);
        }

        try {
            $this->routes->reverseRoute($name, ...[...$arguments, $locale]);
            $encoded = array_map(static fn ($argument): string => rawurlencode((string) $argument), $arguments);
            $path    = $this->routes->reverseRoute($name, ...[...$encoded, $locale]);
        } catch (RouterException $exception) {
            throw new InvalidArgumentException('Invalid dashboard route argument: ' . $name, 0, $exception);
        }
        if (! is_string($path)) {
            throw new InvalidArgumentException('Could not resolve dashboard route: ' . $name);
        }

        $uri = new URI();
        $uri->setQueryArray($query);

        return ['url' => URI::createURIString('', '', $path, $uri->getQuery())] + ($more ? ['label' => $link['label']] : []);
    }
}
