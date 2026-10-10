<?php

use CodeIgniter\I18n\Time;
use Geminus\Admin\Libraries\DataManagement\AttachmentPreview;

?><?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2><div class="text-secondary text-break"><?= esc($user->username) ?></div></div>
        <div class="col-12 col-md-auto"><a class="btn btn-outline-secondary" href="<?= route_to('admin/users') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Admin.userBack')) ?></a></div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <th scope="col"><?= esc(lang('Admin.attachmentFile')) ?></th>
                    <th scope="col"><?= esc(lang('Admin.attachmentSource')) ?></th>
                    <th scope="col"><?= esc(lang('Admin.attachmentRecord')) ?></th>
                    <th scope="col"><?= esc(lang('Admin.attachmentSize')) ?></th>
                    <th scope="col"><?= esc(lang('Admin.attachmentUploadedAt')) ?></th>
                    <th scope="col" class="text-end"><?= esc(lang('Admin.userActions')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($attachments as $attachment): ?>
                        <tr>
                            <td class="text-nowrap" data-label="<?= esc(lang('Admin.attachmentFile'), 'attr') ?>"><?= esc($attachment['original_name']) ?></td>
                            <td data-label="<?= esc(lang('Admin.attachmentSource'), 'attr') ?>"><?= esc(lang($attachment['source']['label'])) ?></td>
                            <td class="text-break" data-label="<?= esc(lang('Admin.attachmentRecord'), 'attr') ?>"><a href="<?= esc(route_to($attachment['source']['recordRoute'], ...$attachment['source']['recordArguments'])) ?>"><?= esc($attachment['source']['title']) ?></a></td>
                            <td class="text-nowrap" data-label="<?= esc(lang('Admin.attachmentSize'), 'attr') ?>"><?= esc(number_format($attachment['size_bytes'] / 1024, 1)) ?> KB</td>
                            <td class="text-nowrap" data-label="<?= esc(lang('Admin.attachmentUploadedAt'), 'attr') ?>"><?= esc($me->formatDateTime(Time::parse($attachment['created_at'], 'UTC'))) ?></td>
                            <td class="text-end" data-label="<?= esc(lang('Admin.userActions'), 'attr') ?>"><div class="btn-list justify-content-end flex-nowrap">
                                <?php if (! empty($attachment['source']['previewRoute']) && AttachmentPreview::mimeType($attachment['mime_type']) !== null): ?>
                                    <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= esc(route_to($attachment['source']['previewRoute'], ...[...$attachment['source']['downloadArguments'], $attachment['id']])) ?>" target="_blank" rel="noopener noreferrer" data-attachment-preview data-preview-name="<?= esc($attachment['original_name'], 'attr') ?>" data-preview-mime="<?= esc($attachment['mime_type'], 'attr') ?>" data-preview-download="<?= esc(route_to($attachment['source']['downloadRoute'], ...[...$attachment['source']['downloadArguments'], $attachment['id']]), 'attr') ?>" aria-label="<?= esc(lang('Admin.attachmentPreview'), 'attr') ?>"><i class="ti ti-eye" aria-hidden="true"></i></a>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= esc(route_to($attachment['source']['downloadRoute'], ...[...$attachment['source']['downloadArguments'], $attachment['id']])) ?>" aria-label="<?= esc(lang('Admin.attachmentDownload'), 'attr') ?>"><i class="ti ti-download" aria-hidden="true"></i></a>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($attachments === []): ?>
                        <tr><td colspan="6"><?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang('Admin.uploadHistoryEmpty')]) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('Admin.uploadHistory')]) ?>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <?php if ($attachments !== []): ?>
        <?= $this->include('Geminus\Admin\Views\attachment_preview') ?>
    <?php endif; ?>
<?= $this->endSection() ?>