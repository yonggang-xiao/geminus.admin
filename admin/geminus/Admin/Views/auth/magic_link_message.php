<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('page_title') ?><?= esc(lang('Auth.useMagicLink')) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-tight py-4">
        <div class="text-center mb-4"><span class="h2">GeminusAdmin</span></div>
        <div class="card card-md">
            <div class="card-body text-center">
                <h1 class="h2 mb-3"><?= esc(lang('Auth.checkYourEmail')) ?></h1>
                <p class="text-secondary mb-0"><?= esc(lang('Auth.magicLinkDetails', [setting('Auth.magicLinkLifetime') / 60])) ?></p>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>