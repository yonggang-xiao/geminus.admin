<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\DataManagement;

use Closure;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class UploadStorage
{
    private readonly string $directory;
    private readonly Closure $removeFile;

    /**
     * @param list<string> $mimeTypes
     * @param list<string> $extensions
     */
    public function __construct(string $folder, private readonly array $mimeTypes, private readonly array $extensions, private readonly int $maxBytes, ?callable $removeFile = null)
    {
        if (! preg_match('/\A[a-zA-Z0-9_-]+\z/', $folder) || $mimeTypes === [] || $extensions === [] || $maxBytes < 1) {
            throw new InvalidArgumentException('Invalid upload configuration.');
        }
        $this->directory  = WRITEPATH . 'uploads/' . $folder . '/';
        $this->removeFile = Closure::fromCallable($removeFile ?? 'unlink');
    }

    public function store(?UploadedFile $file): string
    {
        if ($file === null || ! $file->isValid() || $file->hasMoved()
                           || ! in_array(strtolower($file->getClientExtension()), $this->extensions, true)
                           || ! in_array($file->getMimeType(), $this->mimeTypes, true)
                           || ! is_file($file->getTempName()) || filesize($file->getTempName()) > $this->maxBytes) {
            throw new InvalidArgumentException('Invalid uploaded file.');
        }

        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Could not create upload directory.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . strtolower($file->getClientExtension());
        $file->move($this->directory, $filename);

        return $filename;
    }

    public function path(?string $filename): ?string
    {
        if ($filename === null || ! preg_match('/\A[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+\z/', $filename)
                               || ! in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $this->extensions, true)) {
            return null;
        }
        $root = realpath($this->directory);
        $file = realpath($this->directory . $filename);

        return $root !== false && $file !== false && is_file($file) && ! is_link($this->directory . $filename)
            && str_starts_with($file, $root . DIRECTORY_SEPARATOR) ? $file : null;
    }

    public function delete(?string $filename): void
    {
        $file = $this->path($filename);
        if ($file !== null && ! ($this->removeFile)($file)) {
            throw new RuntimeException('Could not delete uploaded file.');
        }
    }
}
