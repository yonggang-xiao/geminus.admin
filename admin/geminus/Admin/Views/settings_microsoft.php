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
                    <div class="mb-3">
                        <input type="hidden" name="enabled" value="0">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="enabled" value="1"<?= (string) old('enabled', $microsoft['MicrosoftOAuth.enabled']) === '1' ? ' checked' : '' ?> aria-describedby="microsoft-enabled-hint">
                            <span class="form-check-label"><?= esc(lang('Admin.microsoftEnabled')) ?></span>
                        </label>
                        <div id="microsoft-enabled-hint" class="form-text"><?= esc(lang('Admin.microsoftEnabledHint')) ?></div>
                        <?php if (session('microsoft_errors.enabled')): ?><div class="text-danger small"><?= esc(session('microsoft_errors.enabled')) ?></div><?php endif; ?>
                    </div>
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
    <?php if ($requests): ?>
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.microsoftPendingRequests')) ?></h3></div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th><?= esc(lang('Admin.microsoftIdentity')) ?></th><th><?= esc(lang('Admin.microsoftTargetUser')) ?></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><div><?= esc($request['email'] ?? '') ?></div><div class="text-secondary small"><?= esc($request['tenant_id'] . ':' . $request['object_id']) ?></div></td>
                                <td colspan="2">
                                    <form method="post" action="<?= route_to('admin/settings/microsoft/approve', $request['id']) ?>" class="d-flex flex-wrap gap-2 align-items-center">
                                        <?= csrf_field() ?>
                                        <label class="visually-hidden" for="microsoft-request-<?= esc($request['id']) ?>"><?= esc(lang('Admin.microsoftTargetUser')) ?></label>
                                        <select id="microsoft-request-<?= esc($request['id']) ?>" name="user_id" class="form-select w-auto" required>
                                            <option value=""><?= esc(lang('Admin.microsoftTargetUser')) ?></option>
                                            <?php foreach ($candidates as $candidate): ?>
                                                <option value="<?= esc($candidate->id) ?>"><?= esc($candidate->username . ' (' . $candidate->email . ')') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary" onclick="return confirm(<?= esc(json_encode(lang('Admin.microsoftConfirmApproval')), 'attr') ?>)"><?= esc(lang('Admin.microsoftApprove')) ?></button>
                                    </form>
                                    <form method="post" action="<?= route_to('admin/settings/microsoft/reject', $request['id']) ?>" class="mt-2">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><?= esc(lang('Admin.microsoftReject')) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($bindings): ?>
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.microsoftConnectedAccounts')) ?></h3></div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th><?= esc(lang('Admin.microsoftTargetUser')) ?></th><th><?= esc(lang('Admin.microsoftIdentity')) ?></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($bindings as $binding): ?>
                            <tr>
                                <td><?= esc($binding['user']->username) ?></td>
                                <td class="text-secondary small"><?= esc($binding['identity']->secret) ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= route_to('admin/settings/microsoft/revoke', $binding['user']->id) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm(<?= esc(json_encode(lang('Admin.microsoftConfirmRevoke')), 'attr') ?>)"><?= esc(lang('Admin.microsoftRevoke')) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>