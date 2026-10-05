<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('page_title') ?><?= esc(lang('Auth.email2FATitle')) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-tight py-4">
        <div class="text-center mb-4"><span class="h2">GeminusAdmin</span></div>
        <div class="card card-md">
            <div class="card-body">
                <h1 class="h2 text-center mb-3"><?= esc(lang('Auth.emailEnterCode')) ?></h1>
                <p class="text-secondary"><?= esc(lang('Auth.emailConfirmCode')) ?></p>
                <?= $this->include('Geminus\Admin\Views\auth\notice') ?>
                <form action="<?= url_to('auth-action-verify') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required" for="auth-token"><?= esc(lang('Auth.token')) ?></label>
                        <input id="auth-token" name="token" class="form-control" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required>
                    </div>
                    <div class="form-footer"><button type="submit" class="btn btn-primary w-100"><?= esc(lang('Auth.confirm')) ?></button></div>
                </form>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>