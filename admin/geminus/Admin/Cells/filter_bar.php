<form method="get" action="<?= esc($action) ?>" class="row g-2 align-items-end">
    <?php foreach ($fields as $field): ?>
        <div class="<?= esc($field['class'] ?? 'col-12 col-md-3') ?>">
            <?php if (($field['type'] ?? 'text') === 'date'): ?>
                <?= view_cell('Geminus\Admin\Cells\DateFieldCell', ['inputId' => $field['id'], 'name' => $field['name'], 'label' => $field['label'], 'value' => (string) ($field['value'] ?? '')]) ?>
            <?php else: ?>
                <label class="form-label" for="<?= esc($field['id']) ?>"><?= esc($field['label']) ?></label>
                <?php if (($field['type'] ?? 'text') === 'select'): ?>
                    <select class="form-select" id="<?= esc($field['id']) ?>" name="<?= esc($field['name']) ?>">
                        <?php foreach ($field['options'] as $value => $label): ?>
                            <option value="<?= esc($value) ?>"<?= (string) ($field['value'] ?? '') === (string) $value ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input class="form-control" type="<?= esc($field['type'] ?? 'text') ?>" id="<?= esc($field['id']) ?>" name="<?= esc($field['name']) ?>" value="<?= esc($field['value'] ?? '') ?>"<?php if (isset($field['maxlength'])): ?> maxlength="<?= esc($field['maxlength']) ?>"<?php endif; ?><?= ($field['type'] ?? 'text') === 'number' ? ' step="any"' : '' ?>>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php foreach ($hidden as $name => $value): ?>
        <input type="hidden" name="<?= esc($name) ?>" value="<?= esc($value) ?>">
    <?php endforeach; ?>
    <div class="col-12 col-md-auto btn-list">
        <button class="btn btn-primary" type="submit"><i class="ti ti-search me-1" aria-hidden="true"></i><?= esc($submitLabel) ?></button>
        <a class="btn btn-outline-secondary" href="<?= esc($clearUrl) ?>"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc($clearLabel) ?></a>
    </div>
</form>