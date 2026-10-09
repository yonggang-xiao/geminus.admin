<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('page_title') ?><?= esc(lang('Auth.emailActivateTitle')) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-tight py-4">
        <div class="text-center mb-4"><span class="h2">GeminusAdmin</span></div>
        <div class="card card-md">
            <div class="card-body">
                <h1 class="h2 text-center mb-3"><?= esc(lang('Auth.emailActivateTitle')) ?></h1>
                <p class="text-secondary"><?= esc(lang('Auth.emailActivateBody')) ?></p>
                <?= $this->include('Geminus\Admin\Views\auth\notice') ?>
                <form action="<?= url_to('auth-action-verify') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required" for="activate-token"><?= esc(lang('Auth.token')) ?></label>
                        <input id="activate-token" name="token" class="form-control" value="<?= esc(old('token')) ?>" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required>
                    </div>
                    <div class="form-footer"><button type="submit" class="btn btn-primary w-100"><i class="ti ti-send me-1" aria-hidden="true"></i><?= esc(lang('Auth.send')) ?></button></div>
                </form>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>