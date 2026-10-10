<div class="nav-item dropdown" data-notifications>
    <button type="button" class="btn btn-icon px-0 position-relative" data-bs-toggle="dropdown" data-button-tooltip="false" aria-label="<?= esc(lang('Notifications.title'), 'attr') ?>" aria-expanded="false">
        <i class="ti ti-bell icon" aria-hidden="true"></i>
        <?php if ($notifications !== []): ?>
            <span class="badge bg-red badge-notification" data-notification-badge></span>
            <span class="visually-hidden" data-notification-unread-label="<?= esc(lang('Notifications.unreadCount', ['{count}']), 'attr') ?>"><?= esc(lang('Notifications.unreadCount', [count($notifications)])) ?></span>
        <?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-card <?= $mobile ? 'position-fixed' : 'dropdown-menu-arrow' ?>" style="width: 22rem; max-width: calc(100vw - 2rem);<?= $mobile ? ' top: 3.5rem; right: 1rem; left: auto;' : '' ?>">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= esc(lang('Notifications.title')) ?></h3>
                <span class="badge ms-auto" data-notification-count><?= count($notifications) ?></span>
            </div>
            <div class="list-group list-group-flush overflow-auto" style="max-height: 22rem" data-notification-list>
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item" data-notification-item>
                        <form method="post" action="<?= route_to('admin/notifications/open', $notification['id']) ?>" data-notification-target="<?= esc($notification['target'], 'attr') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-wrap p-0">
                                <i class="ti ti-bell me-2" aria-hidden="true"></i>
                                <span class="flex-fill" style="min-width: 0; overflow-wrap: anywhere">
                                    <span class="d-block"><?= esc($notification['title']) ?></span>
                                    <span class="d-block small text-secondary"><?= esc($notification['date']) ?></span>
                                </span>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card-body text-secondary<?= $notifications !== [] ? ' d-none' : '' ?>" data-notification-empty><?= esc(lang('Notifications.empty')) ?></div>
        </div>
    </div>
</div>