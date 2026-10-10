<?php

use CodeIgniter\I18n\Time;

?><?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('head') ?>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/libs/vanilla-calendar-pro/index.js"></script>
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <?php if ($canManage): ?>
        <div class="col-12 col-md-auto ms-auto btn-list">
            <a class="btn btn-primary" href="<?= route_to('admin/announcements/create') ?>"><i class="ti ti-plus me-1" aria-hidden="true"></i><?= esc(lang('Announcements.create')) ?></a>
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-file-type-csv me-1" aria-hidden="true"></i><?= esc(lang('Announcements.batchTools')) ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= route_to('admin/announcements/import') ?>"><i class="ti ti-upload me-2" aria-hidden="true"></i><?= esc(lang('Announcements.import')) ?></a>
                    <a class="dropdown-item" href="<?= route_to('admin/announcements/export') . '?' . esc(http_build_query($filters + ['sort' => $query->sort, 'direction' => $query->direction]), 'attr') ?>"><i class="ti ti-download me-2" aria-hidden="true"></i><?= esc(lang('Announcements.export')) ?></a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="card mb-3">
        <div class="card-body">
            <?= view_cell('Geminus\Admin\Cells\FilterBarCell', [
                'action'      => route_to('admin/announcements'), 'clearUrl' => route_to('admin/announcements'),
                'submitLabel' => lang('Admin.userFilter'), 'clearLabel' => lang('Admin.userClear'),
                'hidden'      => ['sort' => $query->sort, 'direction' => $query->direction],
                'fields'      => [
                    ['id' => 'announcement-search', 'name' => 'q', 'label' => lang('Announcements.search'), 'value' => $query->search, 'maxlength' => 100, 'class' => $canManage ? 'col-12 col-md-4' : 'col-12 col-md-6'],
                    ...($canManage ? [['id' => 'announcement-status', 'name' => 'status', 'label' => lang('Announcements.status'), 'type' => 'select', 'value' => $status, 'options' => ['' => lang('Announcements.allStatuses'), 'draft' => lang('Announcements.status_draft'), 'published' => lang('Announcements.status_published')], 'class' => 'col-12 col-md-2']] : []),
                    ['id' => 'announcement-created-from', 'name' => 'created_from', 'label' => lang($canManage ? 'Announcements.createdFrom' : 'Announcements.publishedFrom'), 'type' => 'date', 'value' => $createdRange['from'], 'class' => 'col-12 col-sm-6 col-md-3'],
                    ['id' => 'announcement-created-to', 'name' => 'created_to', 'label' => lang($canManage ? 'Announcements.createdTo' : 'Announcements.publishedTo'), 'type' => 'date', 'value' => $createdRange['to'], 'class' => 'col-12 col-sm-6 col-md-3'],
                ],
            ]) ?>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/announcements'), 'field' => 'title', 'label' => lang('Announcements.name'), 'sort' => $query->sort, 'direction' => $query->direction, 'filters' => $filters]) ?>
                    <th scope="col"><?= esc(lang('Announcements.body')) ?></th>
                    <?php if ($canManage): ?><th scope="col"><?= esc(lang('Announcements.status')) ?></th><?php endif; ?>
                    <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/announcements'), 'field' => 'published_at', 'label' => lang('Announcements.publishedAt'), 'sort' => $query->sort, 'direction' => $query->direction, 'filters' => $filters, 'defaultDirection' => 'DESC']) ?>
                    <?php if ($canManage): ?>
                    <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/announcements'), 'field' => 'created_at', 'label' => lang('Announcements.createdAt'), 'sort' => $query->sort, 'direction' => $query->direction, 'filters' => $filters, 'defaultDirection' => 'DESC']) ?>
                    <th scope="col" class="w-1"><?= esc(lang('Admin.userActions')) ?></th>
                    <?php endif; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($announcements as $announcement): ?>
                        <tr>
                            <td class="fw-medium text-wrap text-break" style="min-width: 12rem; max-width: 20rem"><a href="<?= route_to('admin/announcements/show', $announcement['id']) ?>"><?= esc($announcement['title']) ?></a></td>
                            <td class="text-secondary text-wrap text-break" style="min-width: 16rem; max-width: 28rem"><?= esc(mb_strimwidth($announcement['body'], 0, 120, '...')) ?></td>
                            <?php if ($canManage): ?><td class="text-nowrap"><span class="badge <?= $announcement['status'] === 'published' ? 'bg-success-lt' : 'bg-secondary-lt' ?>"><?= esc(lang('Announcements.status_' . $announcement['status'])) ?></span></td><?php endif; ?>
                            <td class="text-nowrap"><?= $announcement['published_at'] === null ? '-' : esc($me->formatDateTime(Time::parse($announcement['published_at'], 'UTC'))) ?></td>
                            <?php if ($canManage): ?>
                            <td class="text-nowrap"><?= esc($me->formatDateTime(Time::parse($announcement['created_at'], 'UTC')) ?? '-') ?></td>
                            <td><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/announcements/edit', $announcement['id']) ?>" aria-label="<?= esc(lang('Announcements.edit'), 'attr') ?>"><i class="ti ti-edit" aria-hidden="true"></i></a></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($announcements === []): ?>
                        <tr><td colspan="<?= $canManage ? 6 : 3 ?>"><?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang($filtered ? 'Announcements.noResults' : ($canManage ? 'Announcements.empty' : 'Announcements.emptyPublished')), 'filtered' => $filtered, 'clearUrl' => route_to('admin/announcements'), 'clearLabel' => lang('Admin.userClear'), 'createUrl' => $canManage ? route_to('admin/announcements/create') : '', 'createLabel' => lang('Announcements.create')]) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->only(['q', 'status', 'created_from', 'created_to', 'sort', 'direction'])->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('Announcements.total')]) ?>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        document.querySelectorAll('[data-bs-toggle="datepicker"]').forEach((element) => {
            new tabler.Datepicker(element, {
                locale: document.documentElement.lang,
                dateFormat: (date) => [
                    date.getFullYear(),
                    String(date.getMonth() + 1).padStart(2, '0'),
                    String(date.getDate()).padStart(2, '0'),
                ].join('-'),
            });
        });
    </script>
<?= $this->endSection() ?>