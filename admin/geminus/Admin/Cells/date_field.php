<label class="form-label<?= $required ? ' required' : '' ?>" for="<?= esc($inputId) ?>"><?= esc($label) ?></label>
<div class="input-icon">
    <span class="input-icon-addon"><i class="ti ti-calendar" aria-hidden="true"></i></span>
    <input type="text" id="<?= esc($inputId) ?>" name="<?= esc($name) ?>" class="form-control<?= $error !== '' ? ' is-invalid' : '' ?>" data-bs-toggle="datepicker" autocomplete="off" placeholder="YYYY-MM-DD" value="<?= esc($value) ?>"<?= $required ? ' required' : '' ?><?php if ($min !== ''): ?> data-bs-date-min="<?= esc($min) ?>"<?php endif; ?><?php if ($max !== ''): ?> data-bs-date-max="<?= esc($max) ?>"<?php endif; ?><?php if ($error !== ''): ?> aria-invalid="true"<?php endif; ?><?php if ($describedBy !== ''): ?> aria-describedby="<?= esc($describedBy) ?>"<?php endif; ?>>
</div>
<?php if ($error !== ''): ?><div class="invalid-feedback d-block" id="<?= esc($inputId) ?>-error"><?= esc($error) ?></div><?php endif; ?>
<?php if ($hint !== ''): ?><div class="form-hint" id="<?= esc($inputId) ?>-hint"><?= esc($hint) ?></div><?php endif; ?>