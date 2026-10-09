<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Files\Exceptions\FileNotFoundException;
use CodeIgniter\Files\File;
use CodeIgniter\HTTP\ResponseInterface;
use Geminus\Admin\Libraries\AvatarFiles;

class AvatarController extends BaseController
{
    public function show(int $userId): ResponseInterface
    {
        $user = auth()->getProvider()->findById($userId);
        $path = $user === null ? null : AvatarFiles::storage()->path($user->avatar);
        if ($path === null) {
            return $this->response->setStatusCode(404);
        }

        try {
            $file = new File($path, true);
        } catch (FileNotFoundException $exception) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', $file->getMimeType())
            ->setHeader('Content-Length', (string) $file->getSize())
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(file_get_contents($path));
    }
}
