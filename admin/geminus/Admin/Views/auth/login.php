<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('page_title') ?><?= esc(lang('Auth.login')) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-tight py-4">
        <div class="text-center mb-4"><span class="h2">GeminusAdmin</span></div>
        <div class="card card-md">
            <div class="card-body">
                <h1 class="h2 text-center mb-4"><?= esc(lang('Auth.login')) ?></h1>
                <?= $this->include('Geminus\Admin\Views\auth\notice') ?>
                <form action="<?= url_to('login') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required" for="login-email"><?= esc(lang('Auth.email')) ?></label>
                        <input id="login-email" name="email" type="email" class="form-control" value="<?= esc(old('email')) ?>" inputmode="email" autocomplete="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="login-password"><?= esc(lang('Auth.password')) ?></label>
                        <input id="login-password" name="password" type="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                        <label class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remember" value="1"<?= old('remember') ? ' checked' : '' ?>>
                            <span class="form-check-label"><?= esc(lang('Auth.rememberMe')) ?></span>
                        </label>
                    <?php endif; ?>
                    <div class="form-footer"><button type="submit" class="btn btn-primary w-100"><?= esc(lang('Auth.login')) ?></button></div>
                </form>
                <?php if (setting('Auth.allowMagicLinkLogins')): ?>
                    <p class="text-center text-secondary mt-3 mb-0"><?= esc(lang('Auth.forgotPassword')) ?> <a href="<?= url_to('magic-link') ?>"><?= esc(lang('Auth.useMagicLink')) ?></a></p>
                <?php endif; ?>
                <?php if (setting('Auth.allowRegistration')): ?>
                    <p class="text-center text-secondary mt-3 mb-0"><?= esc(lang('Auth.needAccount')) ?> <a href="<?= url_to('register') ?>"><?= esc(lang('Auth.register')) ?></a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>