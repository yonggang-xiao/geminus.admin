<select id="<?= esc($inputId) ?>" name="timezone" class="form-select<?= $invalid ? ' is-invalid' : '' ?>" required>
<?php foreach ($timezones as $timezone): ?>
    <option value="<?= esc($timezone) ?>" <?= $timezone === $selectedTimezone ? 'selected' : '' ?>>
        <?= esc($timezone) ?>
    </option>
<?php endforeach; ?>
</select>
