<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-auto ms-auto btn-list">
            <a class="btn" href="<?= route_to('admin/announcements/template') ?>"><i class="ti ti-download me-1" aria-hidden="true"></i><?= esc(lang('Announcements.template')) ?></a>
            <a class="btn" href="<?= route_to('admin/announcements') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Announcements.title')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <form action="<?= route_to('admin/announcements/import/store') ?>" method="post" enctype="multipart/form-data" class="mb-4">
        <?= csrf_field() ?>
        <label class="form-label" for="announcement-csv"><?= esc(lang('Admin.attachmentFile')) ?></label>
        <input id="announcement-csv" name="file" type="file" accept=".csv" required class="form-control<?= session('errors.file') ? ' is-invalid' : '' ?>" aria-describedby="announcement-csv-hint<?= session('errors.file') ? ' announcement-csv-error' : '' ?>"<?= session('errors.file') ? ' aria-invalid="true"' : '' ?>>
        <?php if (session('errors.file')): ?><div id="announcement-csv-error" class="invalid-feedback"><?= esc(session('errors.file')) ?></div><?php endif; ?>
        <div id="announcement-csv-hint" class="form-text"><?= esc(lang('Announcements.csvHint')) ?></div>
        <button class="btn btn-primary mt-3" type="submit"><i class="ti ti-upload me-1" aria-hidden="true"></i><?= esc(lang('Announcements.import')) ?></button>
    </form>
    <?php if ($report !== []): ?>
        <?= view_cell('Geminus\Admin\Cells\ImportReportCell', [
            'title'        => lang('Announcements.report'), 'rowLabel' => lang('Admin.userRow'),
            'resultLabel'  => lang('Admin.userResult'), 'reasonLabel' => lang('Admin.userReason'),
            'columns'      => ['title' => lang('Announcements.name')], 'rows' => $report,
            'resultLabels' => ['created' => lang('Announcements.created'), 'error' => lang('Admin.userResult_error')],
            'reasonLabels' => ['created' => lang('Announcements.saved'), 'invalid' => lang('Announcements.invalid'), 'processing' => lang('Announcements.processing')],
        ]) ?>
    <?php endif; ?>
<?= $this->endSection() ?>