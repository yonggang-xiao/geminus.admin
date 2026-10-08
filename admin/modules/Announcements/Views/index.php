<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-auto ms-auto"><a class="btn btn-primary" href="<?= route_to('admin/announcements/create') ?>"><i class="ti ti-plus me-1" aria-hidden="true"></i><?= esc(lang('Announcements.create')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr><th scope="col"><?= esc(lang('Announcements.name')) ?></th><th scope="col"><?= esc(lang('Announcements.body')) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($announcements as $announcement): ?>
                        <tr><td class="fw-medium"><?= esc($announcement['title']) ?></td><td class="text-secondary text-wrap"><?= nl2br(esc($announcement['body'])) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ($announcements === []): ?>
                        <tr><td colspan="2" class="text-center text-secondary py-5"><?= esc(lang('Announcements.empty')) ?> <a href="<?= route_to('admin/announcements/create') ?>"><?= esc(lang('Announcements.create')) ?></a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3"><?= $pager->links() ?></div>
<?= $this->endSection() ?>