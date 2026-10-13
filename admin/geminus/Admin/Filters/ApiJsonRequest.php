<?php

declare(strict_types=1);

namespace Geminus\Admin\Filters;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use stdClass;
use Throwable;

class ApiJsonRequest implements FilterInterface
{
    use ResponseTrait;

    protected ResponseInterface $response;

    public function before(RequestInterface $request, $arguments = null)
    {
        $this->response = service('response')->setHeader('Cache-Control', 'no-store');

        try {
            if (! in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH'], true)) {
                return;
            }
            if (! preg_match('~\Aapplication/json(?:\s*;|\z)~i', $request->getHeaderLine('Content-Type'))) {
                return $this->fail('Content-Type must be application/json.', 415);
            }

            try {
                $payload = $request->getJSON(false, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return $this->fail('The request body must be a JSON object.', 400);
            }
            if (! $payload instanceof stdClass) {
                return $this->fail('The request body must be a JSON object.', 400);
            }
        } catch (Throwable $exception) {
            log_message('error', 'API JSON request failed: {type}', ['type' => $exception::class]);

            return $this->failServerError('The request could not be completed.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
