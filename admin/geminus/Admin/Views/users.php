<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-auto ms-auto btn-list">
            <a class="btn btn-primary" href="<?= route_to('admin/users/create') ?>"><i class="ti ti-user-plus me-1" aria-hidden="true"></i><?= esc(lang('Admin.createUser')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/users/template') ?>"><i class="ti ti-download me-1" aria-hidden="true"></i><?= esc(lang('Admin.userTemplate')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/users/export') . '?' . http_build_query(['q' => $search, 'sort' => $sort, 'direction' => $direction, 'created_from' => $createdRange['from'], 'created_to' => $createdRange['to']]) ?>"><i class="ti ti-file-export me-1" aria-hidden="true"></i><?= esc(lang('Admin.exportUsers')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?= route_to('admin/users') ?>" class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="user-search"><?= esc(lang('Admin.userSearch')) ?></label>
                    <input class="form-control" id="user-search" name="q" value="<?= esc($search) ?>" maxlength="100">
                </div>
                <input type="hidden" name="sort" value="<?= esc($sort) ?>">
                <input type="hidden" name="direction" value="<?= esc($direction) ?>">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="created-from"><?= esc(lang('Admin.createdFrom')) ?></label>
                    <input type="date" id="created-from" name="created_from" class="form-control" value="<?= esc($createdRange['from']) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="created-to"><?= esc(lang('Admin.createdTo')) ?></label>
                    <input type="date" id="created-to" name="created_to" class="form-control" value="<?= esc($createdRange['to']) ?>">
                </div>
                <div class="col-12 col-md-auto btn-list">
                    <button class="btn btn-primary" type="submit"><i class="ti ti-search me-1" aria-hidden="true"></i><?= esc(lang('Admin.userFilter')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= route_to('admin/users') ?>"><?= esc(lang('Admin.userClear')) ?></a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <th scope="col" aria-sort="<?= $sort === 'username' ? ($direction === 'ASC' ? 'ascending' : 'descending') : 'none' ?>">
                        <form method="get" action="<?= route_to('admin/users') ?>">
                            <input type="hidden" name="q" value="<?= esc($search) ?>">
                            <input type="hidden" name="created_from" value="<?= esc($createdRange['from']) ?>">
                            <input type="hidden" name="created_to" value="<?= esc($createdRange['to']) ?>">
                            <input type="hidden" name="direction" value="<?= $sort === 'username' && $direction === 'ASC' ? 'DESC' : 'ASC' ?>">
                            <button type="submit" name="sort" value="username" class="table-sort<?= $sort === 'username' ? ($direction === 'ASC' ? ' asc' : ' desc') : '' ?>"><?= esc(lang('Admin.username')) ?></button>
                        </form>
                    </th>
                    <th scope="col" aria-sort="<?= $sort === 'email' ? ($direction === 'ASC' ? 'ascending' : 'descending') : 'none' ?>">
                        <form method="get" action="<?= route_to('admin/users') ?>">
                            <input type="hidden" name="q" value="<?= esc($search) ?>">
                            <input type="hidden" name="created_from" value="<?= esc($createdRange['from']) ?>">
                            <input type="hidden" name="created_to" value="<?= esc($createdRange['to']) ?>">
                            <input type="hidden" name="direction" value="<?= $sort === 'email' && $direction === 'ASC' ? 'DESC' : 'ASC' ?>">
                            <button type="submit" name="sort" value="email" class="table-sort<?= $sort === 'email' ? ($direction === 'ASC' ? ' asc' : ' desc') : '' ?>"><?= esc(lang('Admin.email')) ?></button>
                        </form>
                    </th>
                    <th scope="col"><?= esc(lang('Admin.userRole')) ?></th>
                    <th scope="col"><?= esc(lang('Admin.userStatus')) ?></th>
                    <th scope="col"><?= esc(lang('Admin.userInviteStatus')) ?></th>
                    <th scope="col" aria-sort="<?= $sort === 'created_at' ? ($direction === 'ASC' ? 'ascending' : 'descending') : 'none' ?>">
                        <form method="get" action="<?= route_to('admin/users') ?>">
                            <input type="hidden" name="q" value="<?= esc($search) ?>">
                            <input type="hidden" name="created_from" value="<?= esc($createdRange['from']) ?>">
                            <input type="hidden" name="created_to" value="<?= esc($createdRange['to']) ?>">
                            <input type="hidden" name="direction" value="<?= $sort === 'created_at' && $direction === 'DESC' ? 'ASC' : 'DESC' ?>">
                            <button type="submit" name="sort" value="created_at" class="table-sort<?= $sort === 'created_at' ? ($direction === 'ASC' ? ' asc' : ' desc') : '' ?>"><?= esc(lang('Admin.userCreated')) ?></button>
                        </form>
                    </th>
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
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= route_to('admin/users/edit', $user->id) ?>"><i class="ti ti-edit me-1" aria-hidden="true"></i><?= esc(lang('Admin.editUser')) ?></a>
                                        <?php if (! $user->isBanned() && $user->email && setting('Auth.allowMagicLinkLogins')): ?>
                                            <form method="post" action="<?= route_to('admin/users/invite', $user->id) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="ti ti-mail-forward me-1" aria-hidden="true"></i><?= esc(lang('Admin.userInvite')) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" data-bs-toggle="offcanvas" data-bs-target="#user-permissions-<?= esc($user->id, 'attr') ?>" aria-controls="user-permissions-<?= esc($user->id, 'attr') ?>" aria-label="<?= esc(lang('Admin.viewUserPermissions') . ': ' . $user->username, 'attr') ?>"><i class="ti ti-eye me-1" aria-hidden="true"></i><?= esc(lang('Admin.viewUserPermissions')) ?></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($users === []): ?>
                        <tr><td colspan="7" class="text-center py-4">
                            <div class="text-secondary mb-2"><?= esc(lang('Admin.noUsersFound')) ?></div>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $search !== '' || $createdRange['from'] !== '' || $createdRange['to'] !== '' ? route_to('admin/users') : route_to('admin/users/create') ?>"><?= esc(lang($search !== '' || $createdRange['from'] !== '' || $createdRange['to'] !== '' ? 'Admin.userClear' : 'Admin.createUser')) ?></a>
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
            <span class="text-secondary"><?= esc(lang('Admin.userTotal')) ?>: <?= esc($pager->getTotal()) ?></span>
            <?= $pager->links() ?>
        </div>
    </div>

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

    <?php if ($report !== null): ?>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.userImportReport')) ?></h3></div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th scope="col"><?= esc(lang('Admin.userRow')) ?></th><th scope="col"><?= esc(lang('Admin.email')) ?></th><th scope="col"><?= esc(lang('Admin.userResult')) ?></th><th scope="col"><?= esc(lang('Admin.userReason')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($report as $entry): ?>
                            <tr><td><?= esc($entry['row']) ?></td><td><?= esc($entry['email']) ?></td><td><?= esc(lang('Admin.userResult_' . $entry['result'])) ?></td><td><?= $entry['reason'] !== '' ? esc(lang('Admin.userReason_' . $entry['reason'])) : '' ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>