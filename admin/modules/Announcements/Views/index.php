<?php

use CodeIgniter\I18n\Time;

?><?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('head') ?>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/libs/vanilla-calendar-pro/index.js"></script>
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md"><h2 class="page-title"><?= esc($page_title) ?></h2></div>
        <div class="col-12 col-md-auto ms-auto btn-list">
            <a class="btn" href="<?= route_to('admin/announcements/export') . '?' . esc(http_build_query($filters + ['sort' => $query->sort, 'direction' => $query->direction]), 'attr') ?>"><i class="ti ti-download me-1" aria-hidden="true"></i><?= esc(lang('Announcements.export')) ?></a>
            <a class="btn" href="<?= route_to('admin/announcements/import') ?>"><i class="ti ti-upload me-1" aria-hidden="true"></i><?= esc(lang('Announcements.import')) ?></a>
            <a class="btn btn-primary" href="<?= route_to('admin/announcements/create') ?>"><i class="ti ti-plus me-1" aria-hidden="true"></i><?= esc(lang('Announcements.create')) ?></a>
        </div>
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
                    ['id' => 'announcement-search', 'name' => 'q', 'label' => lang('Announcements.search'), 'value' => $query->search, 'maxlength' => 100, 'class' => 'col-12 col-md-6'],
                    ['id' => 'announcement-created-from', 'name' => 'created_from', 'label' => lang('Announcements.createdFrom'), 'type' => 'date', 'value' => $createdRange['from'], 'class' => 'col-12 col-sm-6 col-md-3'],
                    ['id' => 'announcement-created-to', 'name' => 'created_to', 'label' => lang('Announcements.createdTo'), 'type' => 'date', 'value' => $createdRange['to'], 'class' => 'col-12 col-sm-6 col-md-3'],
                ],
            ]) ?>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead><tr>
                    <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/announcements'), 'field' => 'title', 'label' => lang('Announcements.name'), 'sort' => $query->sort, 'direction' => $query->direction, 'filters' => $filters]) ?>
                    <th scope="col"><?= esc(lang('Announcements.body')) ?></th>
                    <?= view_cell('Geminus\Admin\Cells\SortHeaderCell', ['action' => route_to('admin/announcements'), 'field' => 'created_at', 'label' => lang('Announcements.createdAt'), 'sort' => $query->sort, 'direction' => $query->direction, 'filters' => $filters, 'defaultDirection' => 'DESC']) ?>
                    <th scope="col" class="w-1"><?= esc(lang('Admin.userActions')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($announcements as $announcement): ?>
                        <tr>
                            <td class="fw-medium text-wrap text-break"><?= esc($announcement['title']) ?></td>
                            <td class="text-secondary text-wrap text-break"><?= nl2br(esc($announcement['body'])) ?></td>
                            <td class="text-nowrap"><?= esc($me->formatDateTime(Time::parse($announcement['created_at'], 'UTC')) ?? '-') ?></td>
                            <td><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= route_to('admin/announcements/attachments', $announcement['id']) ?>" aria-label="<?= esc(lang('Admin.attachments'), 'attr') ?>"><i class="ti ti-paperclip" aria-hidden="true"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($announcements === []): ?>
                        <tr><td colspan="4"><?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang($filtered ? 'Announcements.noResults' : 'Announcements.empty'), 'filtered' => $filtered, 'clearUrl' => route_to('admin/announcements'), 'clearLabel' => lang('Admin.userClear'), 'createUrl' => route_to('admin/announcements/create'), 'createLabel' => lang('Announcements.create')]) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <?= view_cell('Geminus\Admin\Cells\PaginationCell', ['links' => $pager->only(['q', 'created_from', 'created_to', 'sort', 'direction'])->links(), 'total' => $pager->getTotal(), 'currentPage' => $pager->getCurrentPage(), 'perPage' => $pager->getPerPage(), 'totalLabel' => lang('Announcements.total')]) ?>
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