<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-auto ms-auto btn-list">
            <a class="btn btn-primary" href="<?= route_to('admin/users/create') ?>"><i class="ti ti-user-plus me-1" aria-hidden="true"></i><?= esc(lang('Admin.createUser')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/users/template') ?>"><i class="ti ti-download me-1" aria-hidden="true"></i><?= esc(lang('Admin.userTemplate')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/users/export') . '?' . http_build_query(['q' => $search, 'sort' => $sort, 'direction' => $direction]) ?>"><i class="ti ti-file-export me-1" aria-hidden="true"></i><?= esc(lang('Admin.exportUsers')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?= route_to('admin/users') ?>" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="user-search"><?= esc(lang('Admin.userSearch')) ?></label>
                    <input class="form-control" id="user-search" name="q" value="<?= esc($search) ?>" maxlength="100">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="user-sort"><?= esc(lang('Admin.userSort')) ?></label>
                    <select class="form-select" id="user-sort" name="sort">
                        <option value="created_at"<?= $sort === 'created_at' ? ' selected' : '' ?>><?= esc(lang('Admin.userCreated')) ?></option>
                        <option value="username"<?= $sort === 'username' ? ' selected' : '' ?>><?= esc(lang('Admin.username')) ?></option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="user-direction"><?= esc(lang('Admin.userDirection')) ?></label>
                    <select class="form-select" id="user-direction" name="direction">
                        <option value="DESC"<?= $direction === 'DESC' ? ' selected' : '' ?>><?= esc(lang('Admin.userDescending')) ?></option>
                        <option value="ASC"<?= $direction === 'ASC' ? ' selected' : '' ?>><?= esc(lang('Admin.userAscending')) ?></option>
                    </select>
                </div>
                <div class="col-12 col-md-2 btn-list">
                    <button class="btn btn-primary" type="submit"><i class="ti ti-search me-1" aria-hidden="true"></i><?= esc(lang('Admin.userFilter')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= route_to('admin/users') ?>"><?= esc(lang('Admin.userClear')) ?></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th scope="col"><?= esc(lang('Admin.username')) ?></th><th scope="col"><?= esc(lang('Admin.email')) ?></th><th scope="col"><?= esc(lang('Admin.userCreated')) ?></th><th scope="col"></th></tr></thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr><td><?= esc($user->username) ?></td><td><?= esc($user->email ?? '') ?></td><td><?= esc((string) $user->created_at) ?></td><td class="text-end"><?php if ($user->id !== $me->id && ! $user->inGroup('superadmin') && array_diff($user->getGroups() ?? [], ['user', 'admin']) === []): ?><a href="<?= route_to('admin/users/edit', $user->id) ?>"><?= esc(lang('Admin.editUser')) ?></a><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ($users === []): ?>
                        <tr><td colspan="4" class="text-secondary text-center py-4"><?= esc(lang('Admin.noUsersFound')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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