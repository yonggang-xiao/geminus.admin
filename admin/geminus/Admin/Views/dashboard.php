<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"></div>
            <h2 class="page-title"><?= esc($page_title) ?></h2>
        </div>
        <div class="col-auto ms-auto d-print-none"></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <p>Hello World!</p>
<?= $this->endSection() ?>
