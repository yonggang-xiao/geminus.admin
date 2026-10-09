<?= $this->extend('Geminus\Admin\Views\layout_public') ?>

<?= $this->section('page_title') ?><?= lang('Admin.error404') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-tight py-4">
        <div class="empty">
            <div class="empty-img">
                <img src="/static/images/undraw_page-not-found_6wni.svg" alt="" />
            </div>
            <h1 class="empty-title"><?= lang('Admin.error404Title') ?></h1>
            <p class="empty-subtitle text-secondary"><?= lang('Admin.error404Message') ?></p>
            <div class="empty-action">
                <a href="/." class="btn btn-primary btn-lg">
                    <i class="ti ti-arrow-left icon me-1" aria-hidden="true"></i>
                    <?= lang('Admin.takeMeHome') ?>
                </a>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
