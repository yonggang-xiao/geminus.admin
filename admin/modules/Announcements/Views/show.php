<?php

use CodeIgniter\I18n\Time;

?><?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title text-break"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto btn-list">
            <?php if ($canManage): ?>
                <?php if ($announcement['status'] === 'draft'): ?>
                    <form method="post" action="<?= route_to('admin/announcements/publish', $announcement['id']) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary" type="submit"><i class="ti ti-send me-1" aria-hidden="true"></i><?= esc(lang('Announcements.publish')) ?></button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?= route_to('admin/announcements/edit', $announcement['id']) ?>"><i class="ti ti-edit me-1" aria-hidden="true"></i><?= esc(lang('Announcements.edit')) ?></a>
                <span class="d-inline-flex" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('Announcements.delete'), 'attr') ?>"><button class="btn btn-outline-danger btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#announcement-delete" aria-label="<?= esc(lang('Announcements.delete'), 'attr') ?>"><i class="ti ti-trash" aria-hidden="true"></i></button></span>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/announcements') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Announcements.title')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <?php if ($attachments !== []): ?>
        <?= $this->include('Geminus\Admin\Views\attachment_preview') ?>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <?php if ($canManage): ?>
        <div class="modal modal-blur fade" id="announcement-delete" tabindex="-1" aria-labelledby="announcement-delete-title" aria-describedby="announcement-delete-description" aria-hidden="true">
            <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                <form class="modal-content" method="post" action="<?= route_to('admin/announcements/delete', $announcement['id']) ?>">
                    <?= csrf_field() ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('Announcements.cancel'), 'attr') ?>" data-bs-toggle="tooltip" data-bs-title="<?= esc(lang('Announcements.cancel'), 'attr') ?>"></button>
                    <div class="modal-status bg-danger"></div>
                    <div class="modal-body text-center py-4">
                        <i class="ti ti-alert-triangle text-danger fs-1 mb-2 d-block" aria-hidden="true"></i>
                        <h3 id="announcement-delete-title"><?= esc(lang('Announcements.deleteConfirm')) ?></h3>
                        <p class="text-break fw-semibold"><?= esc($announcement['title']) ?></p>
                        <div class="text-secondary" id="announcement-delete-description"><?= esc(lang('Announcements.deleteWarning')) ?></div>
                    </div>
                    <div class="modal-footer">
                        <div class="w-100">
                            <div class="row g-2">
                                <div class="col"><button type="button" class="btn w-100" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc(lang('Announcements.cancel')) ?></button></div>
                                <div class="col"><button type="submit" class="btn btn-danger w-100"><i class="ti ti-trash me-1" aria-hidden="true"></i><?= esc(lang('Announcements.deleteAction')) ?></button></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card card-lg mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-4 text-secondary small">
                        <?php if ($canManage): ?><span class="badge <?= $announcement['status'] === 'published' ? 'bg-success-lt' : 'bg-secondary-lt' ?>"><?= esc(lang('Announcements.status_' . $announcement['status'])) ?></span><?php endif; ?>
                        <?php if ($announcement['published_at'] !== null): ?>
                            <span class="text-secondary"><?= esc(lang('Announcements.publishedAt')) ?>: <?= esc($me->formatDateTime(Time::parse($announcement['published_at'], 'UTC'))) ?></span>
                        <?php endif; ?>
                    </div>
                    <article class="prose text-break"><?= nl2br(esc($announcement['body'])) ?></article>
                </div>
            </div>
            <?php if ($canManage || $attachments !== []): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h3 class="mb-0"><?= esc(lang('Admin.attachments')) ?></h3>
                    <?php if ($canManage): ?>
                        <a class="btn btn-outline-secondary" href="<?= route_to('admin/announcements/attachments', $announcement['id']) ?>"><i class="ti ti-paperclip me-1" aria-hidden="true"></i><?= esc(lang('Announcements.manageAttachments')) ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($attachments !== []): ?>
                <div class="card">
                    <?= view_cell('Geminus\Admin\Cells\AttachmentsCell', [
                        'attachments'  => $attachments, 'downloadRoute' => 'admin/announcements/attachments/download', 'routeArguments' => [$announcement['id']],
                        'previewRoute' => 'admin/announcements/attachments/preview',
                        'labels'       => ['file' => lang('Admin.attachmentFile'), 'size' => lang('Admin.attachmentSize'), 'actions' => lang('Admin.userActions'), 'upload' => lang('Admin.attachmentUpload'), 'download' => lang('Admin.attachmentDownload'), 'remove' => lang('Admin.attachmentRemove'), 'empty' => lang('Admin.attachmentsEmpty')],
                    ]) ?>
                    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('Admin.attachments')]) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?= $this->endSection() ?>