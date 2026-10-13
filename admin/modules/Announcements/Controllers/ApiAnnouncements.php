<?php

declare(strict_types=1);

namespace Modules\Announcements\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Modules\Announcements\Libraries\InvalidAnnouncementInput;
use Throwable;

class ApiAnnouncements extends ResourceController
{
    public function create(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store');
        $user = auth('tokens')->getAuthenticator()->getUser();
        auth('session');

        try {
            $result = service('announcementapipublication')->publish((int) $user->id, get_object_vars($this->request->getJSON(false, 512, JSON_THROW_ON_ERROR)));

            return $this->respondCreated($result);
        } catch (InvalidAnnouncementInput $exception) {
            return $this->fail($exception->fields, 422);
        } catch (Throwable $exception) {
            log_message('error', 'API announcement publication failed: {type}', ['type' => $exception::class]);

            return $this->failServerError('The request could not be completed.');
        }
    }
}
