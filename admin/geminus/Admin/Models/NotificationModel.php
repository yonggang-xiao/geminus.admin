<?php

declare(strict_types=1);

namespace Geminus\Admin\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use RuntimeException;

class NotificationModel extends Model
{
    protected $table         = 'admin_notifications';
    protected $allowedFields = ['user_id', 'source', 'reference', 'title', 'target_route', 'target_parameters', 'permissions', 'created_at', 'read_at'];
    protected $returnType    = 'array';

    public function deliver(array $data): void
    {
        if (! $this->builder()->ignore(true)->insert($data + ['created_at' => Time::now('UTC')->toDateTimeString(), 'read_at' => null])) {
            throw new RuntimeException('Could not save notification.');
        }
    }

    public function unreadFor(int $userId): array
    {
        return $this->where('user_id', $userId)->where('read_at', null)->orderBy('id', 'DESC')->findAll();
    }

    public function read(int $userId, int $notificationId): bool
    {
        return $this->where('user_id', $userId)->where('read_at', null)->update($notificationId, ['read_at' => Time::now('UTC')->toDateTimeString()]);
    }
}
