<?php

declare(strict_types=1);

namespace Geminus\Admin\Models;

use CodeIgniter\Model;
use InvalidArgumentException;

class AttachmentModel extends Model
{
    protected $table         = 'admin_attachments';
    protected $allowedFields = ['resource_type', 'resource_id', 'filename', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'];
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $returnType    = 'array';

    public function forResource(string $type, int $resourceId): self
    {
        if (! preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $type) || $resourceId < 1) {
            throw new InvalidArgumentException('Invalid attachment resource.');
        }

        return $this->where('resource_type', $type)->where('resource_id', $resourceId);
    }
}
