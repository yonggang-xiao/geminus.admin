<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('page_title') ?><?= esc(lang('Auth.useMagicLink')) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-tight py-4">
        <div class="text-center mb-4"><span class="h2">GeminusAdmin</span></div>
        <div class="card card-md">
            <div class="card-body">
                <h1 class="h2 text-center mb-4"><?= esc(lang('Auth.useMagicLink')) ?></h1>
                <?= $this->include('Geminus\Admin\Views\auth\notice') ?>
                <form action="<?= url_to('magic-link') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label required" for="magic-link-email"><?= esc(lang('Auth.email')) ?></label>
                        <input id="magic-link-email" name="email" type="email" class="form-control" value="<?= esc(old('email', auth()->user()->email ?? null)) ?>" autocomplete="email" required>
                    </div>
                    <div class="form-footer"><button type="submit" class="btn btn-primary w-100"><i class="ti ti-send me-1" aria-hidden="true"></i><?= esc(lang('Auth.send')) ?></button></div>
                </form>
                <p class="text-center mt-3 mb-0"><a href="<?= url_to('login') ?>"><?= esc(lang('Auth.backToLogin')) ?></a></p>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>