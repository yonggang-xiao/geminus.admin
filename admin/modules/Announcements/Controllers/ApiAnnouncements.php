<?php

declare(strict_types=1);

namespace Modules\Announcements\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Geminus\Admin\HTTP\ApiResponse;
use Modules\Announcements\Libraries\InvalidAnnouncementInput;
use Throwable;

class ApiAnnouncements extends ResourceController
{
    public function create(): ResponseInterface
    {
        $user = auth('tokens')->getAuthenticator()->getUser();
        auth('session');

        try {
            $result = service('announcementapipublication')->publish((int) $user->id, get_object_vars($this->request->getJSON(false, 512, JSON_THROW_ON_ERROR)));

            return $this->response->setStatusCode(201)->setHeader('Cache-Control', 'no-store')->setJSON($result);
        } catch (InvalidAnnouncementInput $exception) {
            return ApiResponse::error($this->response, 422, 'validation_failed', 'Invalid announcement fields.', $exception->fields);
        } catch (Throwable $exception) {
            log_message('error', 'API announcement publication failed: {type}', ['type' => $exception::class]);

            return ApiResponse::error($this->response, 500, 'internal_error', 'The request could not be completed.');
        }
    }
}
