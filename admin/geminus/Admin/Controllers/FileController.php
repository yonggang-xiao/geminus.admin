<?php

declare(strict_types=1);

namespace Geminus\Admin\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Files\File;

class FileController extends BaseController
{
    public function serve(string $type, string $filename): ResponseInterface
    {
        $allowedTypes = ['avatars', 'documents', 'images'];
        if (! in_array($type, $allowedTypes, true)) {
            return $this->response->setStatusCode(404);
        }

        $filePath = WRITEPATH . 'uploads/'  . $type . '/' . $filename;

        try {
            $file = new File($filePath, true);
        } catch (\CodeIgniter\Files\Exceptions\FileNotFoundException $e) {
            return $this->response->setStatusCode(404);
        }

        $lastModified = gmdate('D, d M Y H:i:s', $file->getATime()) . ' GMT';
        
        $this->response->setHeader('Content-Type', $file->getMimeType());
        $this->response->setHeader('Content-Length', $file->getSize());
        $this->response->setHeader('Last-Modified', $lastModified);
        $this->response->setBody(file_get_contents($file->getRealPath()));
        
        return $this->response;
    }
}
