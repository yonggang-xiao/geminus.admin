<th scope="col" aria-sort="<?= esc($ariaSort, 'attr') ?>">
    <form method="get" action="<?= esc($action, 'attr') ?>">
        <?php foreach ($filters as $name => $value): ?>
            <input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
        <?php endforeach; ?>
        <input type="hidden" name="direction" value="<?= esc($nextDirection, 'attr') ?>">
        <button type="submit" name="sort" value="<?= esc($field, 'attr') ?>" class="table-sort<?= esc($sortClass) ?>"><?= esc($label) ?></button>
    </form>
</th>