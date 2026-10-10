<?php if ($sections === []): ?>
    <?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => lang('Dashboard.empty', [], $locale)]) ?>
<?php endif; ?>
<?php foreach ($sections as $section): ?>
    <section class="mb-4" aria-labelledby="dashboard-<?= esc($section['id'], 'attr') ?>">
        <h3 class="mb-3 text-break" id="dashboard-<?= esc($section['id'], 'attr') ?>"><?= esc($section['label']) ?></h3>
        <?php if ($section['unavailable']): ?>
            <div class="alert alert-warning mb-0" role="status"><?= esc(lang('Dashboard.unavailable', [], $locale)) ?></div>
        <?php else: ?>
            <div class="row row-deck row-cards">
                <?php foreach ($section['items'] as $item): ?>
                    <div class="<?= $item['type'] === 'list' ? 'col-12 col-lg-6' : 'col-12 col-md-6 col-lg-3' ?>">
                        <div class="card" data-dashboard-item="<?= esc($item['key'], 'attr') ?>">
                            <?php if ($item['type'] === 'metric'): ?>
                                <div class="card-body">
                                    <h4 class="subheader text-break mb-2"><?= esc($item['title']) ?></h4>
                                    <div class="h1 mb-3 text-break"><?= esc($item['value']) ?></div>
                                    <p class="text-secondary mb-0 text-break"><?= esc($item['description']) ?></p>
                                </div>
                                <?php if (isset($item['link'])): ?>
                                    <a class="card-btn text-break" href="<?= esc($item['link']['url'], 'attr') ?>"><i class="ti ti-arrow-right me-2" aria-hidden="true"></i><?= esc(lang('Dashboard.viewList', [], $locale)) ?><span class="visually-hidden">: <?= esc($item['title']) ?></span></a>
                                <?php endif; ?>
                            <?php elseif ($item['type'] === 'shortcut'): ?>
                                <div class="card-body d-flex align-items-center">
                                    <a class="btn btn-outline-primary w-100 text-wrap text-break" href="<?= esc($item['link']['url'], 'attr') ?>"><i class="ti ti-<?= esc($item['icon'] ?? 'arrow-right', 'attr') ?> me-2" aria-hidden="true"></i><?= esc($item['title']) ?></a>
                                </div>
                            <?php else: ?>
                                <div class="card-header"><h4 class="card-title text-break"><?= esc($item['title']) ?></h4></div>
                                <?php if ($item['rows'] === []): ?>
                                    <?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => $item['emptyLabel']]) ?>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($item['rows'] as $row): ?>
                                            <li class="list-group-item">
                                                <a class="d-block text-break" href="<?= esc($row['link']['url'], 'attr') ?>"><?= esc($row['title']) ?></a>
                                                <?php if (isset($row['time'])): ?>
                                                    <time class="d-block text-secondary small mt-1" datetime="<?= esc($row['time'], 'attr') ?>"><?= esc($row['displayTime']) ?></time>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if (isset($item['moreLink'])): ?>
                                    <a class="card-btn mt-auto text-break" href="<?= esc($item['moreLink']['url'], 'attr') ?>"><i class="ti ti-arrow-right me-2" aria-hidden="true"></i><?= esc($item['moreLink']['label']) ?><span class="visually-hidden">: <?= esc($item['title']) ?></span></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>