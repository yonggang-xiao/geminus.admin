<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries\DataManagement;

final class AttachmentPreview
{
    public static function mimeType(string $mime): ?string
    {
        return match ($mime) {
            'application/pdf', 'image/jpeg', 'image/png', 'image/webp' => $mime,
            'text/plain', 'text/csv', 'application/vnd.ms-excel'       => 'text/plain',
            default                                                    => null,
        };
    }
}
