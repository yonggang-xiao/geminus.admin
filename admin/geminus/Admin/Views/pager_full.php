<?php

$pager->setSurroundCount(2);
?>

<nav aria-label="<?= esc(lang('Pager.pageNavigation'), 'attr') ?>">
    <ul class="pagination m-0 flex-wrap gap-1">
        <?php if ($pager->hasPrevious()): ?>
            <li class="page-item">
                <a class="page-link" href="<?= esc($pager->getFirst(), 'attr') ?>" aria-label="<?= esc(lang('Pager.first'), 'attr') ?>">
                    <i class="ti ti-chevrons-left" aria-hidden="true"></i>
                </a>
            </li>
            <li class="page-item">
                <a class="page-link" href="<?= esc($pager->getPrevious(), 'attr') ?>" aria-label="<?= esc(lang('Pager.previous'), 'attr') ?>">
                    <i class="ti ti-chevron-left" aria-hidden="true"></i>
                </a>
            </li>
        <?php endif; ?>
        <?php foreach ($pager->links() as $link): ?>
            <li class="page-item<?= $link['active'] ? ' active' : '' ?>">
                <a class="page-link" href="<?= esc($link['uri'], 'attr') ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>><?= esc($link['title']) ?></a>
            </li>
        <?php endforeach; ?>
        <?php if ($pager->hasNext()): ?>
            <li class="page-item">
                <a class="page-link" href="<?= esc($pager->getNext(), 'attr') ?>" aria-label="<?= esc(lang('Pager.next'), 'attr') ?>">
                    <i class="ti ti-chevron-right" aria-hidden="true"></i>
                </a>
            </li>
            <li class="page-item">
                <a class="page-link" href="<?= esc($pager->getLast(), 'attr') ?>" aria-label="<?= esc(lang('Pager.last'), 'attr') ?>">
                    <i class="ti ti-chevrons-right" aria-hidden="true"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>