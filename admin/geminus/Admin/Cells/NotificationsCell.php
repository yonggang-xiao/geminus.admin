<?php

declare(strict_types=1);

namespace Geminus\Admin\Cells;

use CodeIgniter\Exceptions\InvalidArgumentException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Router\Exceptions\RouterException;
use CodeIgniter\View\Cells\Cell;

class NotificationsCell extends Cell
{
    public array $notifications = [];
    public bool $mobile         = false;

    public function mount(): void
    {
        $user   = auth()->user();
        $unread = service('notifications')->unread($user);

        foreach ($unread as $notification) {
            try {
                $target = route_to($notification['target_route'], ...json_decode($notification['target_parameters'], true, flags: JSON_THROW_ON_ERROR));
            } catch (InvalidArgumentException|RouterException $exception) {
                continue;
            }
            if ($target === false) {
                continue;
            }
            $notification['date']   = $user->formatDateTime(Time::parse($notification['created_at'], 'UTC'));
            $notification['target'] = $target;
            $this->notifications[]  = $notification;
        }
    }
}
