<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\InvalidArgumentException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\Exceptions\RouterException;
use RuntimeException;

class Notifications extends BaseController
{
    public function open(int $notificationId): RedirectResponse|ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'private, no-store');
        $notifications = service('notifications');
        $notification  = $notifications->find(auth()->user(), $notificationId);
        if ($notification === null) {
            return $this->errorResponse(404, lang('Notifications.notFound'));
        }

        try {
            $target = route_to($notification['target_route'], ...json_decode($notification['target_parameters'], true, flags: JSON_THROW_ON_ERROR));
        } catch (InvalidArgumentException|RouterException $exception) {
            return $this->errorResponse(404, lang('Notifications.notFound'));
        }
        if ($target === false) {
            return $this->errorResponse(404, lang('Notifications.notFound'));
        }

        try {
            $notification = $notifications->open(auth()->user(), $notificationId);
        } catch (RuntimeException $exception) {
            log_message('error', 'Notification read failed: {exception}', ['exception' => $exception]);

            return $this->errorResponse(500, lang('Notifications.readFailed'));
        }
        if ($notification === null) {
            return $this->errorResponse(404, lang('Notifications.notFound'));
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'message' => lang('Notifications.read'), 'data' => ['target' => $target],
                'csrf'    => ['name' => csrf_token(), 'hash' => csrf_hash()],
            ]);
        }

        return redirect()->to($target);
    }

    private function errorResponse(int $status, string $message): ResponseInterface
    {
        $this->response->setStatusCode($status);
        if (! $this->request->isAJAX()) {
            return $this->response;
        }

        return $this->response->setJSON(['message' => $message, 'errors' => [], 'csrf' => ['name' => csrf_token(), 'hash' => csrf_hash()]]);
    }
}
