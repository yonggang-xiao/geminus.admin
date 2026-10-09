<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use Geminus\Admin\Libraries\DataManagement\UploadStorage;

final class AvatarFiles
{
    private const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_BYTES  = 2 * 1024 * 1024;

    public static function storage(?callable $removeFile = null): UploadStorage
    {
        return new UploadStorage('avatars', self::MIME_TYPES, self::EXTENSIONS, self::MAX_BYTES, $removeFile);
    }

    public static function rules(): string
    {
        return 'uploaded[avatar]|max_size[avatar,' . (self::MAX_BYTES / 1024)
            . ']|is_image[avatar]|mime_in[avatar,' . implode(',', self::MIME_TYPES)
            . ']|ext_in[avatar,' . implode(',', self::EXTENSIONS) . ']';
    }
}
