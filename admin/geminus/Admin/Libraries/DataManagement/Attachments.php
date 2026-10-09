<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\DataManagement;

use CodeIgniter\HTTP\Files\UploadedFile;
use Geminus\Admin\Models\AttachmentModel;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Attachments
{
    public const MAX_BYTES  = 10 * 1024 * 1024;
    public const FILE_TYPES = [
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/vnd.ms-excel'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    private readonly AttachmentModel $model;
    private readonly UploadStorage $storage;

    public function __construct(?AttachmentModel $model = null, ?UploadStorage $storage = null)
    {
        $this->model   = $model ?? new AttachmentModel();
        $this->storage = $storage ?? new UploadStorage('attachments', array_values(array_unique(array_merge(...array_values(self::FILE_TYPES)))), array_keys(self::FILE_TYPES), self::MAX_BYTES);
    }

    public function upload(string $type, int $resourceId, ?UploadedFile $file, int $actorId): int
    {
        $this->model->forResource($type, $resourceId)->resetQuery();
        if ($actorId < 1 || $file === null || ! $file->isValid() || ! in_array($file->getMimeType(), self::FILE_TYPES[strtolower($file->getClientExtension())] ?? [], true)) {
            throw new InvalidArgumentException('Invalid attachment upload.');
        }
        $originalName = basename(str_replace('\\', '/', $file->getClientName()));
        $originalName = preg_replace('/[\x00-\x1F\x7F"]/', '_', $originalName);
        if (! mb_check_encoding($originalName, 'UTF-8') || $originalName === '') {
            throw new InvalidArgumentException('Invalid attachment filename.');
        }
        $mime     = $file->getMimeType();
        $size     = filesize($file->getTempName());
        $filename = $this->storage->store($file);

        try {
            $attachmentId = $this->model->insert([
                'resource_type' => $type, 'resource_id' => $resourceId,
                'filename'      => $filename, 'original_name' => mb_substr($originalName, 0, 180),
                'mime_type'     => $mime, 'size_bytes' => $size, 'uploaded_by' => $actorId,
            ]);
            if ($attachmentId === false) {
                throw new RuntimeException('Could not save attachment.');
            }

            return (int) $attachmentId;
        } catch (Throwable $exception) {
            $this->cleanup($filename);

            throw $exception;
        }
    }

    public function find(string $type, int $resourceId, int $attachmentId): ?array
    {
        return $this->model->forResource($type, $resourceId)->where('id', $attachmentId)->first();
    }

    public function path(array $attachment): ?string
    {
        return $this->storage->path($attachment['filename']);
    }

    public function remove(string $type, int $resourceId, int $attachmentId): bool
    {
        $attachment = $this->find($type, $resourceId, $attachmentId);
        if ($attachment === null) {
            return false;
        }
        if (! $this->model->forResource($type, $resourceId)->where('id', $attachmentId)->delete()) {
            throw new RuntimeException('Could not remove attachment.');
        }
        $this->cleanup($attachment['filename']);

        return true;
    }

    private function cleanup(string $filename): void
    {
        try {
            $this->storage->delete($filename);
        } catch (Throwable $exception) {
            log_message('error', 'Attachment file cleanup failed: {type}', ['type' => $exception::class]);
        }
    }
}
