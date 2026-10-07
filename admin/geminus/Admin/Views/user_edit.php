<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <form method="post" action="<?= route_to('admin/users/update', $user->id) ?>" class="card">
        <?= csrf_field() ?>
        <div class="card-header"><h3 class="card-title"><?= esc($user->username) ?> (<?= esc($user->email ?? '') ?>)</h3></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label required" for="user-username"><?= esc(lang('Admin.username')) ?></label>
                <input id="user-username" name="username" class="form-control<?= session('user_errors.username') ? ' is-invalid' : '' ?>" value="<?= esc(old('username', $user->username)) ?>" maxlength="30" required aria-describedby="user-username-hint">
                <?php if (session('user_errors.username')): ?><div class="invalid-feedback"><?= esc(session('user_errors.username')) ?></div><?php endif; ?>
                <div id="user-username-hint" class="form-text"><?= esc(lang('Admin.usernameHint')) ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label required" for="user-email"><?= esc(lang('Admin.email')) ?></label>
                <input id="user-email" name="email" type="email" class="form-control<?= session('user_errors.email') ? ' is-invalid' : '' ?>" value="<?= esc(old('email', $user->email ?? '')) ?>" maxlength="254" required>
                <?php if (session('user_errors.email')): ?><div class="invalid-feedback"><?= esc(session('user_errors.email')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="user-role"><?= esc(lang('Admin.userRole')) ?></label>
                <select class="form-select<?= session('user_errors.role') ? ' is-invalid' : '' ?>" id="user-role" name="role" required>
                    <option value="user"<?= old('role', $role) === 'user' ? ' selected' : '' ?>><?= esc(lang('Admin.userRoleUser')) ?></option>
                    <option value="admin"<?= old('role', $role) === 'admin' ? ' selected' : '' ?>><?= esc(lang('Admin.userRoleAdmin')) ?></option>
                </select>
                <?php if (session('user_errors.role')): ?><div class="invalid-feedback"><?= esc(session('user_errors.role')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="user-status"><?= esc(lang('Admin.userStatus')) ?></label>
                <select class="form-select<?= session('user_errors.status') ? ' is-invalid' : '' ?>" id="user-status" name="status" required>
                    <option value="enabled"<?= old('status', $user->isBanned() ? 'banned' : 'enabled') === 'enabled' ? ' selected' : '' ?>><?= esc(lang('Admin.userEnabled')) ?></option>
                    <option value="banned"<?= old('status', $user->isBanned() ? 'banned' : 'enabled') === 'banned' ? ' selected' : '' ?>><?= esc(lang('Admin.userBanned')) ?></option>
                </select>
                <?php if (session('user_errors.status')): ?><div class="invalid-feedback"><?= esc(session('user_errors.status')) ?></div><?php endif; ?>
            </div>
            <div class="form-text"><?= esc(lang('Admin.userSessionHint')) ?></div>
        </div>
        <div class="card-footer btn-list">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.saveProfile')) ?></button>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/users') ?>"><?= esc(lang('Admin.userBack')) ?></a>
        </div>
    </form>
<?= $this->endSection() ?>