<div class="empty py-4">
    <h3 class="empty-title text-break"><?= esc($message) ?></h3>
    <?php if ($actionUrl !== '' && $actionLabel !== ''): ?>
        <div class="empty-action">
            <a class="btn btn-outline-secondary btn-sm" href="<?= esc($actionUrl) ?>"><i class="ti ti-<?= esc($actionIcon) ?> me-1" aria-hidden="true"></i><?= esc($actionLabel) ?></a>
        </div>
    <?php endif; ?>
</div>