<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('page_title') ?><?= esc(lang('Auth.register')) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-tight py-4">
        <div class="text-center mb-4"><span class="h2">GeminusAdmin</span></div>
        <div class="card card-md">
            <div class="card-body">
                <h1 class="h2 text-center mb-4"><?= esc(lang('Auth.register')) ?></h1>
                <?= $this->include('Geminus\Admin\Views\auth\notice') ?>
                <form action="<?= url_to('register') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required" for="register-email"><?= esc(lang('Auth.email')) ?></label>
                        <input id="register-email" name="email" type="email" class="form-control" value="<?= esc(old('email')) ?>" inputmode="email" autocomplete="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="register-username"><?= esc(lang('Auth.username')) ?></label>
                        <input id="register-username" name="username" class="form-control" value="<?= esc(old('username')) ?>" autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="register-password"><?= esc(lang('Auth.password')) ?></label>
                        <input id="register-password" name="password" type="password" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="register-password-confirm"><?= esc(lang('Auth.passwordConfirm')) ?></label>
                        <input id="register-password-confirm" name="password_confirm" type="password" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div class="form-footer"><button type="submit" class="btn btn-primary w-100"><?= esc(lang('Auth.register')) ?></button></div>
                </form>
                <p class="text-center text-secondary mt-3 mb-0"><?= esc(lang('Auth.haveAccount')) ?> <a href="<?= url_to('login') ?>"><?= esc(lang('Auth.login')) ?></a></p>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>