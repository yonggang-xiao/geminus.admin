<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class ApiRouteGuard implements FilterInterface
{
    use ResponseTrait;

    protected ResponseInterface $response;

    public function before(RequestInterface $request, $arguments = null)
    {
        $this->response = service('response')->setHeader('Cache-Control', 'no-store');

        try {
            foreach (service('router')->getFilters() as $filter) {
                if (is_string($filter) && preg_match('/\Aapi:[^\s,:*]+\z/', $filter)) {
                    return;
                }
            }

            return $this->failForbidden('The API route is not enabled.');
        } catch (Throwable $exception) {
            log_message('error', 'API route guard failed: {type}', ['type' => $exception::class]);

            return $this->failServerError('The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
