<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2><div class="text-secondary text-break"><?= esc($user->username) ?></div></div>
        <div class="col-12 col-md-auto"><a class="btn btn-outline-secondary" href="<?= route_to('admin/users') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Admin.userBack')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="card-body">
            <?= view_cell('Geminus\Admin\Cells\AttachmentsCell', [
                'uploadUrl'     => $me->can('users.edit') ? route_to('admin/users/attachments/upload', $user->id) : '',
                'downloadRoute' => 'admin/users/attachments/download', 'removeRoute' => $me->can('users.edit') ? 'admin/users/attachments/remove' : '', 'routeArguments' => [$user->id],
                'attachments'   => $attachments, 'accept' => $accept, 'hint' => lang('Admin.attachmentHint'), 'error' => (string) session('attachment_errors.file'),
                'labels'        => ['file' => lang('Admin.attachmentFile'), 'size' => lang('Admin.attachmentSize'), 'actions' => lang('Admin.userActions'), 'upload' => lang('Admin.attachmentUpload'), 'download' => lang('Admin.attachmentDownload'), 'remove' => lang('Admin.attachmentRemove'), 'empty' => lang('Admin.attachmentsEmpty')],
            ]) ?>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('Admin.attachments')]) ?>
        </div>
    </div>
<?= $this->endSection() ?>