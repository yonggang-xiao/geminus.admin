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
            <?= view_cell('Geminus\Admin\Cells\FilterBarCell', [
                'action'      => route_to('admin/audit'), 'clearUrl' => route_to('admin/audit'),
                'submitLabel' => lang('Admin.userFilter'), 'clearLabel' => lang('Admin.userClear'),
                'hidden'      => ['sort' => $sort, 'direction' => $direction],
                'fields'      => [
                    ['type' => 'select', 'id' => 'audit-actor', 'name' => 'actor', 'label' => lang('Admin.auditActor'), 'value' => $actorId, 'options' => $actorOptions, 'class' => 'col-12 col-md-6 col-lg-3'],
                    ['type' => 'select', 'id' => 'audit-target', 'name' => 'object', 'label' => lang('Admin.auditTarget'), 'value' => $object, 'options' => $objectOptions, 'class' => 'col-12 col-md-6 col-lg-3'],
                    ['type' => 'date', 'id' => 'audit-from', 'name' => 'from', 'label' => lang('Admin.auditFrom') . ' (UTC)', 'value' => $from, 'class' => 'col-12 col-sm-6 col-lg-2'],
                    ['type' => 'date', 'id' => 'audit-to', 'name' => 'to', 'label' => lang('Admin.auditTo') . ' (UTC)', 'value' => $to, 'class' => 'col-12 col-sm-6 col-lg-2'],
                    ['type' => 'select', 'id' => 'audit-result', 'name' => 'result', 'label' => lang('Admin.auditResult'), 'value' => $result, 'options' => ['' => lang('Admin.mailAllStatuses'), 'success' => lang('Admin.audit_success'), 'failed' => lang('Admin.audit_failed'), 'redirected' => lang('Admin.audit_redirected')], 'class' => 'col-12 col-sm-6 col-lg-2'],
                ],
            ]) ?>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <?php foreach (['created_at' => lang('Admin.mailCreated'), 'actor' => lang('Admin.auditActor'), 'action' => lang('Admin.auditAction'), 'object' => lang('Admin.auditTarget'), 'path' => lang('Admin.auditPath'), 'result' => lang('Admin.auditResult'), 'ip_address' => 'IP', 'user_agent' => lang('Admin.auditBrowser')] as $column => $label): ?>
                        <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/audit'), 'field' => $column, 'label' => $label, 'sort' => $sort, 'direction' => $direction, 'filters' => ['actor' => $actorId, 'object' => $object, 'from' => $from, 'to' => $to, 'result' => $result]]) ?>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="text-nowrap"><?= esc($me->formatDateTime($row['created_at'])) ?></td>
                            <td><?= esc($row['actor_id'] === null ? '-' : ($userNames[$row['actor_id']] ?? lang('Admin.auditDeletedUser') . ' #' . $row['actor_id'])) ?></td>
                            <td class="text-wrap text-break">
                                <div class="text-nowrap"><?= esc($row['operation'] ?? $row['action']) ?></div>
                                <?php if ($row['submission_display'] !== null): ?>
                                    <div class="text-secondary small"><?= esc($row['action']) ?></div>
                                    <button type="button" class="btn btn-sm btn-ghost-secondary mt-1" data-bs-toggle="collapse" data-bs-target="#audit-submission-<?= (int) $row['id'] ?>" aria-expanded="false" aria-controls="audit-submission-<?= (int) $row['id'] ?>">
                                        <i class="ti ti-chevron-down me-1" aria-hidden="true"></i><?= esc(lang('Admin.auditSubmission')) ?>
                                    </button>
                                    <div class="collapse mt-2" id="audit-submission-<?= (int) $row['id'] ?>">
                                        <dl class="mb-0">
                                            <?php foreach ($row['submission_display']['fields'] as $field => $value): ?>
                                                <dt class="small text-secondary"><?= esc($field) ?></dt>
                                                <dd class="text-wrap text-break mb-1"><?= esc($value) ?></dd>
                                            <?php endforeach; ?>
                                        </dl>
                                        <?php foreach ($row['submission_display']['notes'] as $note): ?>
                                            <div class="small text-secondary text-wrap text-break"><?= esc($note) ?></div>
                                        <?php endforeach; ?>
                                        <?php if ($row['submission_display']['fields'] === [] && $row['submission_display']['notes'] === []): ?>
                                            <span class="text-secondary">-</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($row['target_type']) ?><?= $row['target_id'] !== null ? ' · ' . esc($row['target_type'] === 'users' ? ($userNames[$row['target_id']] ?? lang('Admin.auditDeletedUser') . ' #' . $row['target_id']) : $row['target_id']) : '' ?></td>
                            <td class="text-wrap text-break"><?= esc($row['path']) ?></td>
                            <td><span class="badge <?= match ($row['result']) {
                                'success' => 'bg-success-lt', 'failed' => 'bg-danger-lt', default => 'bg-secondary-lt',
                            } ?>"><?= esc(lang('Admin.audit_' . $row['result'])) ?></span></td>
                            <td><?= esc($row['ip_address']) ?></td>
                            <td class="text-wrap text-break" title="<?= esc($row['user_agent'] ?? '', 'attr') ?>"><?= esc($row['user_agent'] ? mb_strimwidth($row['user_agent'], 0, 56, '...') : '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="8" class="text-center py-4">
                        <?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang('Admin.mailNoRecords'), 'filtered' => $actorId !== '' || $object !== '' || $targetId !== '' || $from !== '' || $to !== '' || $result !== '', 'clearUrl' => route_to('admin/audit'), 'clearLabel' => lang('Admin.userClear')]) ?>
                    </td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager, 'total' => $total, 'currentPage' => $currentPage, 'perPage' => $perPage, 'totalLabel' => lang('Admin.userTotal')]) ?>
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