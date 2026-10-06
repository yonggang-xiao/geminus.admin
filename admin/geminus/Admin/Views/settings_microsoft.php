<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="row">
        <div class="col-12 col-lg-8">
            <form method="post" action="<?= route_to('admin/settings/microsoft/update') ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.microsoftAppRegistration')) ?></h3></div>
                <div class="card-body">
                    <?php foreach (['tenant' => 'microsoftTenant', 'clientId' => 'microsoftClientId'] as $field => $label): ?>
                        <div class="mb-3">
                            <label class="form-label required" for="microsoft-<?= esc($field) ?>"><?= esc(lang('Admin.' . $label)) ?></label>
                            <input id="microsoft-<?= esc($field) ?>" name="<?= esc($field) ?>" type="text" class="form-control<?= session('microsoft_errors.' . $field) ? ' is-invalid' : '' ?>" value="<?= esc(old($field, $microsoft['MicrosoftOAuth.' . $field])) ?>" maxlength="36" required aria-describedby="microsoft-<?= esc($field) ?>-hint">
                            <div id="microsoft-<?= esc($field) ?>-hint" class="form-text"><?= esc(lang('Admin.' . $label . 'Hint')) ?></div>
                            <?php if (session('microsoft_errors.' . $field)): ?><div class="invalid-feedback d-block"><?= esc(session('microsoft_errors.' . $field)) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <p class="form-text mb-0"><?= esc(lang('Admin.microsoftSecretHint')) ?></p>
                    <p class="form-text mb-0"><?= esc(lang('Admin.microsoftPendingHint')) ?></p>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.saveMicrosoftSettings')) ?></button></div>
            </form>
        </div>
    </div>
<?= $this->endSection() ?>