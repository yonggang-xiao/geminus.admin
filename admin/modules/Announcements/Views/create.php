<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <form class="card" method="post" action="<?= route_to('admin/announcements/store') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label required" for="announcement-title"><?= esc(lang('Announcements.name')) ?></label>
                <input id="announcement-title" name="title" class="form-control<?= session('errors.title') ? ' is-invalid' : '' ?>" value="<?= esc(old('title')) ?>" maxlength="150" required>
                <?php if (session('errors.title')): ?><div class="invalid-feedback"><?= esc(session('errors.title')) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label required" for="announcement-body"><?= esc(lang('Announcements.body')) ?></label>
                <textarea id="announcement-body" name="body" class="form-control<?= session('errors.body') ? ' is-invalid' : '' ?>" rows="6" maxlength="2000" required><?= esc(old('body')) ?></textarea>
                <?php if (session('errors.body')): ?><div class="invalid-feedback"><?= esc(session('errors.body')) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Announcements.save')) ?></button>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/announcements') ?>"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc(lang('Announcements.cancel')) ?></a>
        </div>
    </form>
<?= $this->endSection() ?>