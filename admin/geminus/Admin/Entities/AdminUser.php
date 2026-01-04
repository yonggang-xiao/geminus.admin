<?php

declare(strict_types=1);

namespace Geminus\Admin\Entities;

use CodeIgniter\Shield\Entities\User;

class AdminUser extends User
{
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
