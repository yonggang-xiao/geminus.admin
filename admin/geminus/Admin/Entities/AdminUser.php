<?php

declare(strict_types=1);

namespace Geminus\Admin\Entities;

use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class AdminUser extends User
{
    public function formatDateTime(?DateTimeInterface $dateTime, string $format = 'Y-m-d H:i:s'): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new DateTimeZone($this->timezone ?: 'UTC'))
            ->format($format);
    }

    public function getAvatarUrl(): ?string
    {
        helper('file');

        // Return the URL of the user's avatar if set, otherwise null
        $avatar = $this->attributes['avatar'] ?? null;
        if ($avatar) {
            return uploaded_file_url('avatars', $avatar);
        }

        return null;
    }
}
