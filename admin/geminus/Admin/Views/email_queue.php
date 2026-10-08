<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="nav nav-tabs mb-3" role="tablist" aria-label="<?= esc($page_title, 'attr') ?>">
        <a class="nav-link<?= $view === 'logs' ? ' active' : '' ?>" href="<?= route_to('admin/mail/deliveries') ?>"<?= $view === 'logs' ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.mailAudit')) ?></a>
        <a class="nav-link<?= $view === 'queue' ? ' active' : '' ?>" href="<?= route_to('admin/mail/deliveries') ?>?view=queue"<?= $view === 'queue' ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.mailQueueJobs')) ?></a>
    </div>
    <div class="card">
        <?php if ($view === 'logs'): ?>
            <div class="card-body">
                <form method="get" action="<?= route_to('admin/mail/deliveries') ?>" class="row g-2 align-items-end">
                    <div class="col-12 col-sm-4 col-lg-3">
                        <label class="form-label" for="mail-status"><?= esc(lang('Admin.mailStatus')) ?></label>
                        <select class="form-select" id="mail-status" name="status">
                            <option value=""><?= esc(lang('Admin.mailAllStatuses')) ?></option>
                            <?php foreach ($statuses as $option): ?>
                                <option value="<?= $option ?>"<?= $status === $option ? ' selected' : '' ?>><?= esc(lang('Admin.mailStatus_' . $option)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-5 col-lg-4">
                        <label class="form-label" for="mail-recipient"><?= esc(lang('Admin.mailRecipient')) ?></label>
                        <input class="form-control" id="mail-recipient" name="recipient" value="<?= esc($recipient, 'attr') ?>" maxlength="254">
                    </div>
                    <div class="col-12 col-sm-auto btn-list">
                        <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1" aria-hidden="true"></i><?= esc(lang('Admin.userFilter')) ?></button>
                        <a class="btn btn-outline-secondary" href="<?= route_to('admin/mail/deliveries') ?>"><?= esc(lang('Admin.userClear')) ?></a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <?php if ($view === 'logs'): ?>
                    <thead><tr><th scope="col">#</th><th scope="col"><?= esc(lang('Admin.mailRecipient')) ?></th><th scope="col"><?= esc(lang('Admin.mailSubject')) ?></th><th scope="col"><?= esc(lang('Admin.mailStatus')) ?></th><th scope="col"><?= esc(lang('Admin.mailAttempts')) ?></th><th scope="col"><?= esc(lang('Admin.mailCreated')) ?></th><th scope="col"><?= esc(lang('Admin.mailProcessed')) ?></th><th scope="col"><?= esc(lang('Admin.mailFailureReason')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= esc($row['id']) ?></td>
                                <td class="text-wrap"><?= esc($row['recipient']) ?></td>
                                <td class="text-wrap"><?= esc($row['subject']) ?></td>
                                <td><span class="badge <?= match ($row['status']) {
                                    'sent' => 'bg-success-lt', 'failed' => 'bg-danger-lt', default => 'bg-secondary-lt',
                                } ?>"><?= esc(lang('Admin.mailStatus_' . $row['status'])) ?></span></td>
                                <td><?= esc($row['attempts']) ?></td>
                                <td class="text-nowrap"><?= esc($me->formatDateTime($row['created_at'])) ?></td>
                                <td class="text-nowrap"><?= esc($me->formatDateTime($row['processed_at']) ?? '-') ?></td>
                                <td class="text-wrap"><?= esc($row['failure_reason'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="8" class="text-center text-secondary py-4"><?= esc(lang('Admin.mailNoRecords')) ?></td></tr><?php endif; ?>
                    </tbody>
                <?php else: ?>
                    <thead><tr><th scope="col">#</th><th scope="col"><?= esc(lang('Admin.mailRecipient')) ?></th><th scope="col"><?= esc(lang('Admin.mailSubject')) ?></th><th scope="col"><?= esc(lang('Admin.mailStatus')) ?></th><th scope="col"><?= esc(lang('Admin.mailAttempts')) ?></th><th scope="col"><?= esc(lang('Admin.mailCreated')) ?></th><th scope="col"><?= esc(lang('Admin.mailAvailable')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= esc($row['id']) ?></td>
                                <td class="text-wrap"><?= esc($row['recipient'] ?? '-') ?></td>
                                <td class="text-wrap"><?= esc($row['subject'] ?? '-') ?></td>
                                <td><span class="badge <?= (int) $row['status'] === 1 ? 'bg-warning-lt' : 'bg-secondary-lt' ?>"><?= esc(lang('Admin.mailJob_' . match ((int) $row['status']) {
                                    0 => 'pending', 1 => 'reserved', default => 'unknown',
                                })) ?></span></td>
                                <td><?= esc($row['attempts']) ?></td>
                                <td class="text-nowrap"><?= esc($me->formatDateTime($row['created_at'])) ?></td>
                                <td class="text-nowrap"><?= esc($me->formatDateTime($row['available_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-secondary py-4"><?= esc(lang('Admin.mailNoRecords')) ?></td></tr><?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-secondary"><?= esc(lang('Admin.userTotal')) ?>: <?= esc($total) ?></span>
            <?= $pager ?>
        </div>
    </div>
<?= $this->endSection() ?>