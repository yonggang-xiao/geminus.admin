<?php if ($sections === []): ?>
    <?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang('Dashboard.empty', [], $locale)]) ?>
<?php endif; ?>
<div class="row row-cards">
    <?php if ($metrics !== []): ?>
        <div class="col-12">
            <div class="row row-deck row-cards">
                <?php foreach ($metrics as $item): ?>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-sm" data-dashboard-item="<?= esc($item['key'], 'attr') ?>">
                            <div class="card-status-top bg-<?= esc($item['moduleColor'], 'attr') ?>" aria-hidden="true"></div>
                            <div class="card-body">
                                <div class="visually-hidden"><?= esc($item['moduleLabel']) ?></div>
                                <div class="row g-2 align-items-center mb-2">
                                    <div class="col"><h3 class="fw-normal fs-4 text-break mb-0"><?= esc($item['title']) ?></h3></div>
                                    <div class="col-auto">
                                    <?php if (isset($item['link'])): ?>
                                        <a class="btn btn-sm btn-icon btn-ghost-secondary" href="<?= esc($item['link']['url'], 'attr') ?>" aria-label="<?= esc(lang('Dashboard.viewList', [], $locale) . ': ' . $item['moduleLabel'] . ': ' . $item['title'], 'attr') ?>"><i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                                    <?php else: ?>
                                        <span class="btn btn-sm btn-icon btn-ghost-secondary invisible" aria-hidden="true"><i class="ti ti-arrow-right"></i></span>
                                    <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mb-2"><div class="h1 mb-0 text-break text-<?= esc($item['moduleColor'], 'attr') ?>"><?= esc($item['value']) ?></div></div>
                                <p class="text-secondary small mb-0 text-break"><?= esc($item['description']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php foreach ($sections as $section): ?>
        <?php if (! $section['unavailable'] && $section['panels'] === [] && $section['shortcuts'] === []): ?>
            <?php continue; ?>
        <?php endif; ?>
        <section class="<?= $section['panels'] === [] && ! $section['unavailable'] ? 'col-12' : 'col-12 col-lg-6' ?>" aria-labelledby="dashboard-<?= esc($section['id'], 'attr') ?>">
            <div class="row g-2 align-items-center mb-2">
                <div class="<?= $section['panels'] === [] && ! $section['unavailable'] ? 'visually-hidden' : 'col' ?>">
                    <h3 class="subheader mb-0 text-break" id="dashboard-<?= esc($section['id'], 'attr') ?>"><?= esc($section['label']) ?></h3>
                </div>
                <?php if ($section['shortcuts'] !== []): ?>
                    <div class="col-12 col-sm-auto ms-sm-auto">
                        <div class="btn-list">
                            <?php foreach ($section['shortcuts'] as $item): ?>
                                <a class="btn btn-sm btn-primary text-wrap text-break mw-100" data-dashboard-item="<?= esc($item['key'], 'attr') ?>" href="<?= esc($item['link']['url'], 'attr') ?>"><i class="ti ti-<?= esc($item['icon'] ?? 'arrow-right', 'attr') ?> me-2" aria-hidden="true"></i><?= esc($item['title']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif ($section['panels'] !== [] || $section['unavailable']): ?>
                    <div class="col-auto" aria-hidden="true">
                        <span class="btn btn-sm invisible"><i class="ti ti-plus me-2"></i><span><?= esc($section['label']) ?></span></span>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($section['unavailable']): ?>
                <div class="alert alert-warning mb-0" role="status"><?= esc(lang('Dashboard.unavailable', [], $locale)) ?></div>
            <?php else: ?>
                <div class="row row-cards">
                    <?php foreach ($section['panels'] as $item): ?>
                        <div class="col-12">
                            <div class="card" data-dashboard-item="<?= esc($item['key'], 'attr') ?>">
                                <div class="card-status-top bg-<?= esc($section['color'], 'attr') ?>" aria-hidden="true"></div>
                                <div class="card-header flex-wrap gap-2">
                                    <h4 class="card-title text-break"><?php if (isset($item['icon'])): ?><i class="ti ti-<?= esc($item['icon'], 'attr') ?> text-<?= esc($section['color'], 'attr') ?> me-2" aria-hidden="true"></i><?php endif; ?><?= esc($item['title']) ?></h4>
                                    <?php if (isset($item['moreLink'])): ?>
                                        <div class="card-actions ms-auto">
                                            <a class="btn btn-sm text-wrap text-break" href="<?= esc($item['moreLink']['url'], 'attr') ?>"><i class="ti ti-arrow-right me-2" aria-hidden="true"></i><?= esc($item['moreLink']['label']) ?><span class="visually-hidden">: <?= esc($item['title']) ?></span></a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['type'] === 'progress' && isset($item['link'])): ?>
                                        <div class="card-actions ms-auto">
                                            <a class="btn btn-sm text-wrap text-break" href="<?= esc($item['link']['url'], 'attr') ?>"><i class="ti ti-arrow-right me-2" aria-hidden="true"></i><?= esc(lang('Dashboard.viewList', [], $locale)) ?><span class="visually-hidden">: <?= esc($item['title']) ?></span></a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($item['type'] === 'progress'): ?>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                                            <span class="text-secondary text-break"><?= esc($item['displayCount']) ?></span>
                                            <?php if ($item['percentage'] !== null): ?>
                                                <span class="h1 mb-0 ms-auto"><?= esc($item['displayPercentage']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($item['percentage'] === null): ?>
                                            <p class="text-secondary mb-0 text-break"><?= esc(lang('Dashboard.progressEmpty', [], $locale)) ?></p>
                                        <?php else: ?>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar bg-<?= esc($item['color'], 'attr') ?>" style="width: <?= esc($item['percentage'], 'attr') ?>%" role="progressbar" aria-valuenow="<?= esc($item['value'], 'attr') ?>" aria-valuemin="0" aria-valuemax="<?= esc($item['max'], 'attr') ?>" aria-label="<?= esc($item['title'], 'attr') ?>" aria-valuetext="<?= esc($item['displayCount'], 'attr') ?>"></div>
                                            </div>
                                        <?php endif; ?>
                                        <p class="text-secondary small mt-3 mb-0 text-break"><?= esc($item['description']) ?></p>
                                    </div>
                                <?php elseif ($item['rows'] === []): ?>
                                    <?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => $item['emptyLabel']]) ?>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush list-group-hoverable card-list-group">
                                        <?php foreach ($item['rows'] as $row): ?>
                                            <li class="list-group-item">
                                                <?php if ($item['type'] === 'status-list'): ?>
                                                    <div class="row g-2 align-items-center">
                                                        <div class="col-12 col-sm">
                                                <?php endif; ?>
                                                <a class="text-body d-block text-break" href="<?= esc($row['link']['url'], 'attr') ?>"><?= esc($row['title']) ?></a>
                                                <?php if (isset($row['time'])): ?>
                                                    <time class="d-block text-secondary small mt-1" datetime="<?= esc($row['time'], 'attr') ?>"><?= esc($row['displayTime']) ?></time>
                                                <?php endif; ?>
                                                <?php if ($item['type'] === 'status-list'): ?>
                                                        </div>
                                                        <div class="col-12 col-sm-auto">
                                                            <span class="badge bg-<?= esc($row['color'], 'attr') ?>-lt text-wrap text-break mw-100"><?php if (isset($row['icon'])): ?><i class="ti ti-<?= esc($row['icon'], 'attr') ?> me-1 flex-shrink-0" aria-hidden="true"></i><?php endif; ?><span class="text-break"><?= esc($row['status']) ?></span></span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>