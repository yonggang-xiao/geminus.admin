<?php

declare(strict_types=1);

namespace Geminus\Admin\Libraries;

use Geminus\Admin\Entities\AdminUser;
use Geminus\Admin\Models\NotificationModel;
use InvalidArgumentException;
use RuntimeException;

class Notifications
{
    public function __construct(private readonly NotificationModel $model)
    {
    }

    public function send(int $userId, string $source, int $reference, string $title, string $targetRoute, array $permissions = [], array $targetParameters = []): void
    {
        if ($userId < 1 || $reference < 1 || ! preg_match('/\A[a-zA-Z0-9_.-]{1,64}\z/', $source)
                        || trim($title) === '' || mb_strlen($title) > 200 || ! preg_match('/\Aadmin\/[a-zA-Z0-9_\/-]{1,144}\z/', $targetRoute)) {
            throw new InvalidArgumentException('Invalid notification.');
        }

        foreach ($permissions as $permission) {
            if (! is_string($permission) || ! preg_match('/\A[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\z/', $permission)) {
                throw new InvalidArgumentException('Invalid notification permission.');
            }
        }

        foreach ($targetParameters as $parameter) {
            if (! is_int($parameter) && ! is_string($parameter)) {
                throw new InvalidArgumentException('Invalid notification target parameter.');
            }
        }

        $this->model->deliver([
            'user_id'           => $userId, 'source' => $source, 'reference' => $reference,
            'title'             => $title, 'target_route' => $targetRoute, 'permissions' => json_encode(array_values($permissions), JSON_THROW_ON_ERROR),
            'target_parameters' => json_encode(array_values($targetParameters), JSON_THROW_ON_ERROR),
        ]);
    }

    public function unread(AdminUser $user): array
    {
        return array_values(array_filter($this->model->unreadFor((int) $user->id), fn (array $notification): bool => $this->canRead($user, $notification)));
    }

    public function open(AdminUser $user, int $notificationId): ?array
    {
        $notification = $this->find($user, $notificationId);
        if ($notification === null) {
            return null;
        }
        if (! $this->model->read((int) $user->id, $notificationId)) {
            throw new RuntimeException('Could not mark notification as read.');
        }

        return $notification;
    }

    public function find(AdminUser $user, int $notificationId): ?array
    {
        $notification = $this->model->where('user_id', $user->id)->find($notificationId);

        return $notification !== null && $this->canRead($user, $notification) ? $notification : null;
    }

    private function canRead(AdminUser $user, array $notification): bool
    {
        $permissions = json_decode($notification['permissions'], true, flags: JSON_THROW_ON_ERROR);

        return $permissions === [] || $user->can(...$permissions);
    }
}
