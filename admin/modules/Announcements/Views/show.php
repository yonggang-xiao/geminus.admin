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
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= route_to('admin/announcements') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Announcements.title')) ?></a>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <?php if ($canManage): ?><span class="badge <?= $announcement['status'] === 'published' ? 'bg-success-lt' : 'bg-secondary-lt' ?>"><?= esc(lang('Announcements.status_' . $announcement['status'])) ?></span><?php endif; ?>
        <?php if ($announcement['published_at'] !== null): ?>
            <span class="text-secondary"><?= esc(lang('Announcements.publishedAt')) ?>: <?= esc($me->formatDateTime(Time::parse($announcement['published_at'], 'UTC'))) ?></span>
        <?php endif; ?>
    </div>
    <article class="text-break mb-4"><?= nl2br(esc($announcement['body'])) ?></article>
    <?php if ($canManage || $attachments !== []): ?>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h3 class="mb-0"><?= esc(lang('Admin.attachments')) ?></h3>
            <?php if ($canManage): ?>
                <a class="btn btn-outline-secondary" href="<?= route_to('admin/announcements/attachments', $announcement['id']) ?>"><i class="ti ti-paperclip me-1" aria-hidden="true"></i><?= esc(lang('Announcements.manageAttachments')) ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($attachments !== []): ?>
        <?= view_cell('Geminus\Admin\Cells\AttachmentsCell', [
            'attachments' => $attachments, 'downloadRoute' => 'admin/announcements/attachments/download', 'routeArguments' => [$announcement['id']],
            'labels'      => ['file' => lang('Admin.attachmentFile'), 'size' => lang('Admin.attachmentSize'), 'actions' => lang('Admin.userActions'), 'upload' => lang('Admin.attachmentUpload'), 'download' => lang('Admin.attachmentDownload'), 'remove' => lang('Admin.attachmentRemove'), 'empty' => lang('Admin.attachmentsEmpty')],
        ]) ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('Admin.attachments')]) ?>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>