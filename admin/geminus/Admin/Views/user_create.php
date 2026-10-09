<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <form method="post" action="<?= route_to('admin/users/store') ?>" class="card">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label required" for="new-username"><?= esc(lang('Admin.username')) ?></label>
                <input id="new-username" name="username" class="form-control<?= session('user_errors.username') ? ' is-invalid' : '' ?>" value="<?= esc(old('username')) ?>" maxlength="30" required aria-describedby="new-username-hint">
                <?php if (session('user_errors.username')): ?><div class="invalid-feedback"><?= esc(session('user_errors.username')) ?></div><?php endif; ?>
                <div id="new-username-hint" class="form-text"><?= esc(lang('Admin.usernameHint')) ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label required" for="new-email"><?= esc(lang('Admin.email')) ?></label>
                <input id="new-email" name="email" type="email" class="form-control<?= session('user_errors.email') ? ' is-invalid' : '' ?>" value="<?= esc(old('email')) ?>" maxlength="255" required>
                <?php if (session('user_errors.email')): ?><div class="invalid-feedback"><?= esc(session('user_errors.email')) ?></div><?php endif; ?>
            </div>
            <div class="form-text"><?= esc(lang('Admin.userProvisionHint')) ?></div>
        </div>
        <div class="card-footer btn-list">
            <button class="btn btn-primary" type="submit"><i class="ti ti-user-plus me-1" aria-hidden="true"></i><?= esc(lang('Admin.createUser')) ?></button>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/users') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Admin.userBack')) ?></a>
        </div>
    </form>
<?= $this->endSection() ?>