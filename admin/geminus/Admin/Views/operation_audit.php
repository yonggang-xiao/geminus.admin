<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('head') ?>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/libs/vanilla-calendar-pro/index.js"></script>
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="card-body">
            <form method="get" action="<?= route_to('admin/audit') ?>" class="row g-2 align-items-end">
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="audit-actor"><?= esc(lang('Admin.auditActor')) ?></label>
                    <select class="form-select" id="audit-actor" name="actor">
                        <option value=""><?= esc(lang('Admin.auditAllActors')) ?></option>
                        <?php foreach ($actors as $actor): ?>
                            <option value="<?= esc($actor['actor_id'], 'attr') ?>"<?= (string) $actorId === (string) $actor['actor_id'] ? ' selected' : '' ?>><?= esc($userNames[$actor['actor_id']] ?? lang('Admin.auditDeletedUser') . ' #' . $actor['actor_id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label" for="audit-target"><?= esc(lang('Admin.auditTarget')) ?></label>
                    <select class="form-select" id="audit-target" name="object">
                        <option value=""><?= esc(lang('Admin.auditAllObjects')) ?></option>
                        <?php $previousType = null; ?>
                        <?php foreach ($objects as $item): ?>
                            <?php if ($previousType !== $item['target_type']): ?>
                                <option value="<?= esc($item['target_type'], 'attr') ?>|"<?= $object === $item['target_type'] . '|' ? ' selected' : '' ?>><?= esc($item['target_type']) ?></option>
                                <?php $previousType = $item['target_type']; ?>
                            <?php endif; ?>
                            <?php if ($item['target_id'] !== null): ?>
                                <?php $value = $item['target_type'] . '|' . $item['target_id']; ?>
                                <option value="<?= esc($value, 'attr') ?>"<?= $object === $value ? ' selected' : '' ?>><?= esc($item['target_type']) ?> · <?= esc($item['target_type'] === 'users' ? ($userNames[$item['target_id']] ?? lang('Admin.auditDeletedUser') . ' #' . $item['target_id']) : $item['target_id']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="audit-from"><?= esc(lang('Admin.auditFrom')) ?> (UTC)</label>
                    <input class="form-control" type="text" id="audit-from" name="from" data-bs-toggle="datepicker" autocomplete="off" placeholder="YYYY-MM-DD" value="<?= esc($from, 'attr') ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="audit-to"><?= esc(lang('Admin.auditTo')) ?> (UTC)</label>
                    <input class="form-control" type="text" id="audit-to" name="to" data-bs-toggle="datepicker" autocomplete="off" placeholder="YYYY-MM-DD" value="<?= esc($to, 'attr') ?>">
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
                <input type="hidden" name="sort" value="<?= esc($sort, 'attr') ?>">
                <input type="hidden" name="direction" value="<?= esc($direction, 'attr') ?>">
                <div class="col-12 col-lg-auto btn-list">
                    <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1" aria-hidden="true"></i><?= esc(lang('Admin.userFilter')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= route_to('admin/audit') ?>"><?= esc(lang('Admin.userClear')) ?></a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <?php foreach (['created_at' => lang('Admin.mailCreated'), 'actor' => lang('Admin.auditActor'), 'action' => lang('Admin.auditAction'), 'object' => lang('Admin.auditTarget'), 'path' => lang('Admin.auditPath'), 'result' => lang('Admin.auditResult'), 'ip_address' => 'IP', 'user_agent' => lang('Admin.auditBrowser')] as $column => $label): ?>
                        <th scope="col" aria-sort="<?= $sort === $column ? ($direction === 'ASC' ? 'ascending' : 'descending') : 'none' ?>">
                            <form method="get" action="<?= route_to('admin/audit') ?>">
                                <?php foreach (['actor' => $actorId, 'object' => $object, 'from' => $from, 'to' => $to, 'result' => $result] as $filter => $value): ?>
                                    <input type="hidden" name="<?= $filter ?>" value="<?= esc($value, 'attr') ?>">
                                <?php endforeach; ?>
                                <input type="hidden" name="direction" value="<?= $sort === $column && $direction === 'ASC' ? 'DESC' : 'ASC' ?>">
                                <button type="submit" name="sort" value="<?= esc($column, 'attr') ?>" class="table-sort<?= $sort === $column ? ($direction === 'ASC' ? ' asc' : ' desc') : '' ?>"><?= esc($label) ?></button>
                            </form>
                        </th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="text-nowrap"><?= esc($me->formatDateTime($row['created_at'])) ?></td>
                            <td><?= esc($row['actor_id'] === null ? '-' : ($userNames[$row['actor_id']] ?? lang('Admin.auditDeletedUser') . ' #' . $row['actor_id'])) ?></td>
                            <td><?= esc($row['action']) ?></td>
                            <td><?= esc($row['target_type']) ?><?= $row['target_id'] !== null ? ' · ' . esc($row['target_type'] === 'users' ? ($userNames[$row['target_id']] ?? lang('Admin.auditDeletedUser') . ' #' . $row['target_id']) : $row['target_id']) : '' ?></td>
                            <td class="text-wrap text-break"><?= esc($row['path']) ?></td>
                            <td><span class="badge <?= match ($row['result']) {
                                'success' => 'bg-success-lt', 'failed' => 'bg-danger-lt', default => 'bg-secondary-lt',
                            } ?>"><?= esc(lang('Admin.audit_' . $row['result'])) ?></span></td>
                            <td><?= esc($row['ip_address']) ?></td>
                            <td class="text-wrap text-break" title="<?= esc($row['user_agent'] ?? '', 'attr') ?>"><?= esc($row['user_agent'] ? mb_strimwidth($row['user_agent'], 0, 56, '...') : '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="8" class="text-center text-secondary py-4"><?= esc(lang('Admin.mailNoRecords')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-secondary"><?= esc(lang('Admin.userTotal')) ?>: <?= esc($total) ?></span>
            <?= $pager ?>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        for (const field of ['audit-from', 'audit-to']) {
            new tabler.Datepicker(document.getElementById(field), {
                dateFormat: (date) => [
                    date.getFullYear(),
                    String(date.getMonth() + 1).padStart(2, '0'),
                    String(date.getDate()).padStart(2, '0'),
                ].join('-'),
            });
        }
    </script>
<?= $this->endSection() ?>