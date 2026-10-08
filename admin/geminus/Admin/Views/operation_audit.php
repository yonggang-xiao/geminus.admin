<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="card-body">
            <form method="get" action="<?= route_to('admin/audit') ?>" class="row g-2 align-items-end">
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="audit-actor"><?= esc(lang('Admin.auditActor')) ?></label>
                    <input class="form-control" id="audit-actor" name="actor" inputmode="numeric" value="<?= esc($actorId, 'attr') ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="audit-target"><?= esc(lang('Admin.auditTarget')) ?></label>
                    <input class="form-control" id="audit-target" name="target" maxlength="32" value="<?= esc($targetId, 'attr') ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="audit-type"><?= esc(lang('Admin.auditType')) ?></label>
                    <input class="form-control" id="audit-type" name="type" maxlength="64" value="<?= esc($type, 'attr') ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="audit-from"><?= esc(lang('Admin.auditFrom')) ?> (UTC)</label>
                    <input class="form-control" type="date" id="audit-from" name="from" value="<?= esc($from, 'attr') ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="audit-to"><?= esc(lang('Admin.auditTo')) ?> (UTC)</label>
                    <input class="form-control" type="date" id="audit-to" name="to" value="<?= esc($to, 'attr') ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="audit-result"><?= esc(lang('Admin.auditResult')) ?></label>
                    <select class="form-select" id="audit-result" name="result">
                        <option value=""><?= esc(lang('Admin.mailAllStatuses')) ?></option>
                        <?php foreach (['success', 'failed', 'redirected'] as $option): ?>
                            <option value="<?= $option ?>"<?= $result === $option ? ' selected' : '' ?>><?= esc(lang('Admin.audit_' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-auto btn-list">
                    <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1" aria-hidden="true"></i><?= esc(lang('Admin.userFilter')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= route_to('admin/audit') ?>"><?= esc(lang('Admin.userClear')) ?></a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th scope="col"><?= esc(lang('Admin.mailCreated')) ?></th><th scope="col"><?= esc(lang('Admin.auditActor')) ?></th><th scope="col"><?= esc(lang('Admin.auditAction')) ?></th><th scope="col"><?= esc(lang('Admin.auditTarget')) ?></th><th scope="col"><?= esc(lang('Admin.auditPath')) ?></th><th scope="col"><?= esc(lang('Admin.auditResult')) ?></th><th scope="col">IP</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="text-nowrap"><?= esc($me->formatDateTime($row['created_at'])) ?></td>
                            <td><?= esc($row['actor_id'] ?? '-') ?></td>
                            <td><?= esc($row['action']) ?></td>
                            <td><?= esc($row['target_type']) ?><?= $row['target_id'] !== null ? ' #' . esc($row['target_id']) : '' ?></td>
                            <td class="text-wrap text-break"><?= esc($row['path']) ?></td>
                            <td><span class="badge <?= match ($row['result']) {
                                'success' => 'bg-success-lt', 'failed' => 'bg-danger-lt', default => 'bg-secondary-lt',
                            } ?>"><?= esc(lang('Admin.audit_' . $row['result'])) ?></span></td>
                            <td><?= esc($row['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-secondary py-4"><?= esc(lang('Admin.mailNoRecords')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-secondary"><?= esc(lang('Admin.userTotal')) ?>: <?= esc($total) ?></span>
            <?= $pager ?>
        </div>
    </div>
<?= $this->endSection() ?>