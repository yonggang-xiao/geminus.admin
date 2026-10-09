<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('head') ?>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/libs/vanilla-calendar-pro/index.js"></script>
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.profileDetails')) ?></h3></div>
                <div class="card-body">
                    <div class="mb-4 pb-3 border-bottom">
                        <div class="form-label"><?= esc(lang('Admin.avatar')) ?></div>
                        <div class="d-flex flex-wrap align-items-start gap-3">
                            <?= view_cell('Geminus\Admin\Cells\AvatarCell', ['user' => $me, 'size' => 'xl']) ?>
                            <div class="flex-fill">
                                <form method="post" action="<?= route_to('admin/profile/avatar') ?>" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <label class="form-label required" for="profile-avatar"><?= esc(lang('Admin.chooseAvatar')) ?></label>
                                    <input id="profile-avatar" type="file" name="avatar" class="form-control<?= session('avatar_errors.avatar') ? ' is-invalid' : '' ?>" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-hint" required>
                                    <?php if (session('avatar_errors.avatar')): ?><div class="invalid-feedback"><?= esc(session('avatar_errors.avatar')) ?></div><?php endif; ?>
                                    <div id="avatar-hint" class="form-text"><?= esc(lang('Admin.avatarHint')) ?></div>
                                    <button type="submit" class="btn btn-outline-primary mt-2"><i class="ti ti-upload me-1" aria-hidden="true"></i><?= esc(lang('Admin.uploadAvatar')) ?></button>
                                </form>
                                <?php if ($me->avatar): ?>
                                    <form method="post" action="<?= route_to('admin/profile/avatar/remove') ?>" class="mt-2">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="ti ti-trash me-1" aria-hidden="true"></i><?= esc(lang('Admin.removeAvatar')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <form method="post" action="<?= route_to('admin/profile/update') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label required" for="profile-username"><?= esc(lang('Admin.username')) ?></label>
                            <input id="profile-username" name="username" class="form-control<?= session('profile_errors.username') ? ' is-invalid' : '' ?>" value="<?= esc(old('username', $me->username)) ?>" required maxlength="30" aria-describedby="profile-username-hint">
                            <?php if (session('profile_errors.username')): ?><div class="invalid-feedback"><?= esc(session('profile_errors.username')) ?></div><?php endif; ?>
                            <div id="profile-username-hint" class="form-text"><?= esc(lang('Admin.usernameHint')) ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profile-email"><?= esc(lang('Admin.email')) ?></label>
                            <input id="profile-email" class="form-control" value="<?= esc($me->email ?? '') ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="profile-language"><?= esc(lang('Admin.language')) ?></label>
                            <select id="profile-language" name="language" class="form-select<?= session('profile_errors.language') ? ' is-invalid' : '' ?>" required>
                                <?php foreach ($languages as $language): ?>
                                    <option value="<?= esc($language) ?>" <?= old('language', $me->language) === $language ? 'selected' : '' ?>><?= esc(lang('Admin.localeName', [], $language)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (session('profile_errors.language')): ?><div class="invalid-feedback"><?= esc(session('profile_errors.language')) ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required" for="profile-timezone"><?= esc(lang('Admin.timezone')) ?></label>
                            <?= view_cell('Geminus\Admin\Cells\TimezoneSelectorCell', [
                                'selectedTimezone' => old('timezone', $me->timezone),
                                'inputId'          => 'profile-timezone',
                                'invalid'          => (bool) session('profile_errors.timezone'),
                            ]) ?>
                            <?php if (session('profile_errors.timezone')): ?><div class="invalid-feedback"><?= esc(session('profile_errors.timezone')) ?></div><?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.saveProfile')) ?></button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($localAccount): ?>
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.changePassword')) ?></h3></div>
                    <div class="card-body">
                        <form method="post" action="<?= route_to('admin/profile/password') ?>">
                            <?= csrf_field() ?>
                            <?php foreach (['current_password' => 'currentPassword', 'new_password' => 'newPassword', 'confirm_password' => 'confirmPassword'] as $field => $label): ?>
                                <div class="mb-3">
                                    <label class="form-label required" for="<?= esc($field) ?>"><?= esc(lang('Admin.' . $label)) ?></label>
                                    <input id="<?= esc($field) ?>" type="password" name="<?= esc($field) ?>" class="form-control<?= session('password_errors.' . $field) ? ' is-invalid' : '' ?>" required autocomplete="<?= $field === 'current_password' ? 'current-password' : 'new-password' ?>"<?= $field === 'new_password' ? ' aria-describedby="new-password-hint"' : '' ?>>
                                    <?php if (session('password_errors.' . $field)): ?><div class="invalid-feedback"><?= esc(session('password_errors.' . $field)) ?></div><?php endif; ?>
                                    <?php if ($field === 'new_password'): ?><div id="new-password-hint" class="form-text"><?= esc(lang('Admin.passwordHint', [$minimumPasswordLength])) ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.savePassword')) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($microsoftEnabled): ?>
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.microsoftLogin')) ?></h3></div>
                    <div class="card-body">
                        <?php if ($microsoftLinked): ?>
                            <span class="badge bg-success-lt"><?= esc(lang('Admin.microsoftLinked')) ?></span>
                        <?php elseif ($localAccount): ?>
                            <form method="post" action="<?= route_to('admin/profile/microsoft/connect') ?>">
                                <?= csrf_field() ?>
                                <label class="form-label required" for="microsoft-current-password"><?= esc(lang('Admin.currentPassword')) ?></label>
                                <input id="microsoft-current-password" type="password" name="current_password" class="form-control" required autocomplete="current-password">
                                <button type="submit" class="btn btn-outline-primary mt-3"><i class="ti ti-brand-windows me-1" aria-hidden="true"></i><?= esc(lang('Admin.microsoftConnect')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.apiTokens')) ?></h3></div>
                <div class="card-body">
                    <form method="post" action="<?= route_to('admin/profile/tokens') ?>" class="row g-3 align-items-end">
                        <?= csrf_field() ?>
                        <div class="col-12 col-md-5">
                            <label class="form-label required" for="token-name"><?= esc(lang('Admin.tokenName')) ?></label>
                            <input id="token-name" name="name" class="form-control<?= session('token_errors.name') ? ' is-invalid' : '' ?>" required maxlength="100">
                            <?php if (session('token_errors.name')): ?><div class="invalid-feedback"><?= esc(session('token_errors.name')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12 col-md-4">
                            <?= view_cell('Geminus\Admin\Cells\DateFieldCell', ['inputId' => 'token-expires', 'name' => 'expires', 'label' => lang('Admin.tokenExpires'), 'value' => (string) old('expires'), 'error' => (string) session('token_errors.expires'), 'min' => gmdate('Y-m-d'), 'required' => true]) ?>
                        </div>
                        <div class="col-12 col-md-auto"><button type="submit" class="btn btn-primary"><i class="ti ti-plus me-1" aria-hidden="true"></i><?= esc(lang('Admin.createToken')) ?></button></div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th><?= esc(lang('Admin.tokenName')) ?></th><th><?= esc(lang('Admin.tokenExpires')) ?></th><th><?= esc(lang('Admin.lastUsed')) ?></th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($tokens as $token): ?>
                                <tr>
                                    <td><?= esc($token->name) ?></td>
                                    <td><?= esc($token->expires?->format('Y-m-d') ?? '-') ?></td>
                                    <td><?= esc($me->formatDateTime($token->last_used_at) ?? '-') ?></td>
                                    <td class="text-end">
                                        <form method="post" action="<?= route_to('admin/profile/tokens/revoke', $token->id) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm(<?= esc(json_encode(lang('Admin.confirmRevoke')), 'attr') ?>)"><i class="ti ti-trash me-1" aria-hidden="true"></i><?= esc(lang('Admin.revokeToken')) ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($tokens === []): ?><tr><td colspan="4" class="text-secondary text-center py-4"><?= esc(lang('Admin.noTokens')) ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        new tabler.Datepicker(document.getElementById('token-expires'), {
            dateFormat: (date) => [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-'),
        });
    </script>
<?= $this->endSection() ?>