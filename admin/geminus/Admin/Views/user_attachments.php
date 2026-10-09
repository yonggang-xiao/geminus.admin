<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title"><?= esc($page_title) ?></h2><div class="text-secondary text-break"><?= esc($user->username) ?></div></div>
        <div class="col-auto"><a class="btn btn-outline-secondary" href="<?= route_to('admin/users/edit', $user->id) ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Admin.editUser')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <form method="post" action="<?= route_to('admin/users/attachments/upload', $user->id) ?>" enctype="multipart/form-data" class="mb-4 pb-4 border-bottom">
        <?= csrf_field() ?>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label required" for="attachment-file"><?= esc(lang('Admin.attachmentFile')) ?></label>
                <input type="file" name="file" id="attachment-file" class="form-control<?= session('attachment_errors.file') ? ' is-invalid' : '' ?>" accept="<?= esc($accept, 'attr') ?>" required aria-describedby="attachment-hint<?= session('attachment_errors.file') ? ' attachment-error' : '' ?>">
                <?php if (session('attachment_errors.file')): ?><div class="invalid-feedback" id="attachment-error"><?= esc(session('attachment_errors.file')) ?></div><?php endif; ?>
                <div class="form-text" id="attachment-hint"><?= esc(lang('Admin.attachmentHint')) ?></div>
            </div>
            <div class="col-12 col-md-auto"><button type="submit" class="btn btn-primary"><i class="ti ti-upload me-1" aria-hidden="true"></i><?= esc(lang('Admin.attachmentUpload')) ?></button></div>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-vcenter" style="table-layout: fixed">
            <thead><tr><th scope="col"><?= esc(lang('Admin.attachmentFile')) ?></th><th scope="col" class="w-25"><?= esc(lang('Admin.attachmentSize')) ?></th><th scope="col" class="w-25 text-end"><?= esc(lang('Admin.userActions')) ?></th></tr></thead>
            <tbody>
                <?php foreach ($attachments as $attachment): ?>
                    <tr>
                        <td class="text-break"><?= esc($attachment['original_name']) ?></td>
                        <td><?= esc(number_format($attachment['size_bytes'] / 1024, 1)) ?> KB</td>
                        <td><div class="btn-list justify-content-end flex-nowrap">
                            <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/users/attachments/download', $user->id, $attachment['id']) ?>" aria-label="<?= esc(lang('Admin.attachmentDownload'), 'attr') ?>" title="<?= esc(lang('Admin.attachmentDownload'), 'attr') ?>"><i class="ti ti-download" aria-hidden="true"></i></a>
                            <form method="post" action="<?= route_to('admin/users/attachments/remove', $user->id, $attachment['id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?= esc(lang('Admin.attachmentRemove'), 'attr') ?>" title="<?= esc(lang('Admin.attachmentRemove'), 'attr') ?>"><i class="ti ti-trash" aria-hidden="true"></i></button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($attachments === []): ?><tr><td colspan="3" class="text-center text-secondary py-4"><?= esc(lang('Admin.attachmentsEmpty')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <span class="text-secondary"><?= esc(lang('Admin.userTotal')) ?>: <?= esc($pager->getTotal()) ?></span>
        <?= $pager->links() ?>
    </div>
<?= $this->endSection() ?>