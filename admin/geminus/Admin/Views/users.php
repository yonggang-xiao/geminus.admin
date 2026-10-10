<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('head') ?>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/libs/vanilla-calendar-pro/index.js"></script>
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto btn-list">
            <?php if ($me->can('users.create')): ?>
            <a class="btn btn-primary" href="<?= route_to('admin/users/create') ?>"><i class="ti ti-user-plus me-1" aria-hidden="true"></i><?= esc(lang('Admin.createUser')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/users/template') ?>"><i class="ti ti-download me-1" aria-hidden="true"></i><?= esc(lang('Admin.userTemplate')) ?></a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/users/export') . '?' . http_build_query(['q' => $search, 'sort' => $sort, 'direction' => $direction, 'created_from' => $createdRange['from'], 'created_to' => $createdRange['to']]) ?>"><i class="ti ti-file-export me-1" aria-hidden="true"></i><?= esc(lang('Admin.exportUsers')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card mb-3">
        <div class="card-body">
            <?= view_cell('Geminus\Admin\Cells\FilterBarCell', [
                'action'      => route_to('admin/users'), 'clearUrl' => route_to('admin/users'),
                'submitLabel' => lang('Admin.userFilter'), 'clearLabel' => lang('Admin.userClear'),
                'hidden'      => ['sort' => $sort, 'direction' => $direction],
                'fields'      => [
                    ['id' => 'user-search', 'name' => 'q', 'label' => lang('Admin.userSearch'), 'value' => $search, 'maxlength' => 100, 'class' => 'col-12 col-md-6'],
                    ['type' => 'date', 'id' => 'created-from', 'name' => 'created_from', 'label' => lang('Admin.createdFrom'), 'value' => $createdRange['from'], 'class' => 'col-12 col-sm-6 col-md-3'],
                    ['type' => 'date', 'id' => 'created-to', 'name' => 'created_to', 'label' => lang('Admin.createdTo'), 'value' => $createdRange['to'], 'class' => 'col-12 col-sm-6 col-md-3'],
                ],
            ]) ?>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <?php foreach (['username' => 'Admin.username', 'email' => 'Admin.email'] as $field => $label): ?>
                        <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/users'), 'field' => $field, 'label' => lang($label), 'sort' => $sort, 'direction' => $direction, 'filters' => ['q' => $search, 'created_from' => $createdRange['from'], 'created_to' => $createdRange['to']]]) ?>
                    <?php endforeach; ?>
                    <th scope="col"><?= esc(lang('Admin.userRole')) ?></th>
                    <th scope="col"><?= esc(lang('Admin.userStatus')) ?></th>
                    <th scope="col"><?= esc(lang('Admin.userInviteStatus')) ?></th>
                    <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/users'), 'field' => 'created_at', 'label' => lang('Admin.userCreated'), 'sort' => $sort, 'direction' => $direction, 'defaultDirection' => 'DESC', 'filters' => ['q' => $search, 'created_from' => $createdRange['from'], 'created_to' => $createdRange['to']]]) ?>
                    <th scope="col" class="text-end"><?= esc(lang('Admin.userActions')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= esc($user->username) ?></td>
                            <td><?= esc($user->email ?? '') ?></td>
                            <td><?= esc($roleNames[$user->id]) ?></td>
                            <td><span class="badge <?= $user->isBanned() ? 'bg-danger-lt' : 'bg-success-lt' ?>"><?= esc(lang($user->isBanned() ? 'Admin.userBanned' : 'Admin.userEnabled')) ?></span></td>
                            <td><?php if (isset($invitationStatuses[$user->id])): ?>
                                <span class="badge <?= match ($invitationStatuses[$user->id]) {
                                    'sent' => 'bg-success-lt', 'failed' => 'bg-danger-lt', default => 'bg-secondary-lt',
                                } ?>"><?= esc(lang('Admin.mailStatus_' . $invitationStatuses[$user->id])) ?></span>
                            <?php else: ?><?= esc(lang('Admin.userInviteNotSent')) ?><?php endif; ?></td>
                            <td><?= esc($me->formatDateTime($user->created_at) ?? '-') ?></td>
                            <td class="text-end">
                                <div class="btn-list justify-content-end flex-nowrap">
                                    <?php if ($editStates[$user->id] === 'self'): ?>
                                        <span class="text-secondary text-nowrap"><?= esc(lang('Admin.userSelf')) ?></span>
                                    <?php elseif ($editStates[$user->id] === 'protected'): ?>
                                        <span class="text-secondary text-nowrap"><?= esc(lang('Admin.userProtected')) ?></span>
                                    <?php elseif ($me->can('users.edit')): ?>
                                        <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/users/edit', $user->id) ?>" aria-label="<?= esc(lang('Admin.editUser') . ': ' . $user->username, 'attr') ?>" title="<?= esc(lang('Admin.editUser'), 'attr') ?>"><i class="ti ti-edit" aria-hidden="true"></i></a>
                                        <?php if (! $user->isBanned() && $user->email && setting('Auth.allowMagicLinkLogins')): ?>
                                            <form method="post" action="<?= route_to('admin/users/invite', $user->id) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?= esc(lang('Admin.userInvite') . ': ' . $user->username, 'attr') ?>" title="<?= esc(lang('Admin.userInvite'), 'attr') ?>"><i class="ti ti-mail-forward" aria-hidden="true"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($attachmentAccess[$user->id]): ?>
                                        <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/users/attachments', $user->id) ?>" aria-label="<?= esc(lang('Admin.uploadHistory') . ': ' . $user->username, 'attr') ?>"><i class="ti ti-paperclip" aria-hidden="true"></i></a>
                                    <?php endif; ?>
                                    <span class="d-inline-flex" data-button-tooltip title="<?= esc(lang('Admin.viewUserPermissions'), 'attr') ?>">
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#user-permissions-<?= esc($user->id, 'attr') ?>" aria-controls="user-permissions-<?= esc($user->id, 'attr') ?>" aria-label="<?= esc(lang('Admin.viewUserPermissions') . ': ' . $user->username, 'attr') ?>"><i class="ti ti-eye" aria-hidden="true"></i></button>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($users === []): ?>
                        <tr><td colspan="7" class="text-center py-4">
                            <?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang('Admin.noUsersFound'), 'filtered' => $search !== '' || $createdRange['from'] !== '' || $createdRange['to'] !== '', 'createUrl' => $me->can('users.create') ? route_to('admin/users/create') : '', 'createLabel' => lang('Admin.createUser'), 'createIcon' => 'user-plus', 'clearUrl' => route_to('admin/users'), 'clearLabel' => lang('Admin.userClear')]) ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php foreach ($users as $user): ?>
            <div class="offcanvas offcanvas-end" tabindex="-1" id="user-permissions-<?= esc($user->id, 'attr') ?>" aria-labelledby="user-permissions-title-<?= esc($user->id, 'attr') ?>">
                <div class="offcanvas-header">
                    <h2 class="offcanvas-title" id="user-permissions-title-<?= esc($user->id, 'attr') ?>"><?= esc($user->username) ?> <span class="d-block text-secondary small"><?= esc(lang('Admin.userPermissions')) ?></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= esc(lang('Admin.close'), 'attr') ?>"></button>
                </div>
                <div class="offcanvas-body">
                    <p class="text-secondary small"><?= esc(lang('Admin.userPermissionsHint')) ?></p>
                    <?php if ($effectivePermissions[$user->id] === []): ?>
                        <p class="text-secondary"><?= esc(lang('Admin.noCatalogPermissions')) ?></p>
                    <?php else: ?>
                        <p class="text-secondary"><?= esc(sprintf(lang('Admin.userPermissionCount'), count($effectivePermissions[$user->id]))) ?></p>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($effectivePermissions[$user->id] as $permission): ?><li class="list-group-item px-0"><code><?= esc($permission) ?></code></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('Admin.userTotal')]) ?>
        </div>
    </div>

    <?php if ($me->can('users.create')): ?>
    <form method="post" action="<?= route_to('admin/users/import') ?>" enctype="multipart/form-data" class="card mb-3">
        <?= csrf_field() ?>
        <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.importUsers')) ?></h3></div>
        <div class="card-body">
            <label class="form-label" for="user-file"><?= esc(lang('Admin.userCsvFile')) ?></label>
            <input class="form-control" type="file" id="user-file" name="file" accept=".csv,text/csv" required aria-describedby="user-csv-hint">
            <div id="user-csv-hint" class="form-text"><?= esc(lang('Admin.userCsvHint')) ?></div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="ti ti-file-import me-1" aria-hidden="true"></i><?= esc(lang('Admin.importUsers')) ?></button></div>
    </form>
    <?php endif; ?>

    <?php if ($report !== null): ?>
        <?= view_cell('Geminus\Admin\Cells\ImportReportCell', [
            'title'        => lang('Admin.userImportReport'), 'rowLabel' => lang('Admin.userRow'),
            'resultLabel'  => lang('Admin.userResult'), 'reasonLabel' => lang('Admin.userReason'),
            'columns'      => ['email' => lang('Admin.email')], 'rows' => $report,
            'resultLabels' => ['created' => lang('Admin.userResult_created'), 'skipped' => lang('Admin.userResult_skipped'), 'error' => lang('Admin.userResult_error')],
            'reasonLabels' => ['duplicate' => lang('Admin.userReason_duplicate'), 'invalid' => lang('Admin.userReason_invalid'), 'username' => lang('Admin.userReason_username'), 'save' => lang('Admin.userReason_save')],
        ]) ?>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        for (const field of ['created-from', 'created-to']) {
            new tabler.Datepicker(document.getElementById(field), {
                locale: document.documentElement.lang,
                dateFormat: (date) => [
                    date.getFullYear(),
                    String(date.getMonth() + 1).padStart(2, '0'),
                    String(date.getDate()).padStart(2, '0'),
                ].join('-'),
            });
        }
    </script>
<?= $this->endSection() ?>