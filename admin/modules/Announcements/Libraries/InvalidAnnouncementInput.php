<?php

declare(strict_types=1);

namespace Modules\Announcements\Libraries;

use InvalidArgumentException;

class InvalidAnnouncementInput extends InvalidArgumentException
{
    public function __construct(public readonly array $fields)
    {
        parent::__construct('Invalid announcement fields.');
    }
}
