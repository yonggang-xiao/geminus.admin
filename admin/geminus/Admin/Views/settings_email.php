<?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="row">
        <div class="col-12 col-lg-8">
            <form method="post" action="<?= route_to('admin/settings/email/update') ?>" class="card">
                <?= csrf_field() ?>
                <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.emailDelivery')) ?></h3></div>
                <div class="card-body">
                    <?php foreach (['fromEmail' => 'senderEmail', 'fromName' => 'senderName'] as $field => $label): ?>
                        <div class="mb-3">
                            <label class="form-label required" for="email-<?= esc($field) ?>"><?= esc(lang('Admin.' . $label)) ?></label>
                            <input id="email-<?= esc($field) ?>" name="<?= esc($field) ?>" type="<?= $field === 'fromEmail' ? 'email' : 'text' ?>" class="form-control<?= session('email_errors.' . $field) ? ' is-invalid' : '' ?>" value="<?= esc(old($field, $email['Email.' . $field])) ?>" maxlength="<?= $field === 'fromEmail' ? '254' : '100' ?>" required>
                            <?php if (session('email_errors.' . $field)): ?><div class="invalid-feedback"><?= esc(session('email_errors.' . $field)) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="mb-3">
                        <label class="form-label required" for="email-protocol"><?= esc(lang('Admin.mailProtocol')) ?></label>
                        <select id="email-protocol" name="protocol" class="form-select<?= session('email_errors.protocol') ? ' is-invalid' : '' ?>" required>
                            <option value="mail" <?= old('protocol', $email['Email.protocol']) === 'mail' ? 'selected' : '' ?>>mail</option>
                            <option value="smtp" <?= old('protocol', $email['Email.protocol']) === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                        </select>
                        <?php if (session('email_errors.protocol')): ?><div class="invalid-feedback"><?= esc(session('email_errors.protocol')) ?></div><?php endif; ?>
                    </div>
                    <div id="email-smtp-settings"<?= old('protocol', $email['Email.protocol']) === 'smtp' ? '' : ' class="d-none"' ?>>
                        <h4 class="mt-4 mb-3"><?= esc(lang('Admin.smtpSettings')) ?></h4>
                        <?php foreach (['SMTPHost' => 'smtpHost', 'SMTPUser' => 'smtpUser'] as $field => $label): ?>
                            <div class="mb-3">
                                <label class="form-label<?= $field === 'SMTPHost' ? ' required' : '' ?>" for="email-<?= esc($field) ?>"><?= esc(lang('Admin.' . $label)) ?></label>
                                <input id="email-<?= esc($field) ?>" name="<?= esc($field) ?>" class="form-control<?= session('email_errors.' . $field) ? ' is-invalid' : '' ?>" value="<?= esc(old($field, $email['Email.' . $field])) ?>" maxlength="255"<?= $field === 'SMTPHost' ? ' required' : '' ?>>
                                <?php if (session('email_errors.' . $field)): ?><div class="invalid-feedback"><?= esc(session('email_errors.' . $field)) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label required" for="email-SMTPPort"><?= esc(lang('Admin.smtpPort')) ?></label>
                                <input id="email-SMTPPort" name="SMTPPort" type="number" min="1" max="65535" class="form-control<?= session('email_errors.SMTPPort') ? ' is-invalid' : '' ?>" value="<?= esc(old('SMTPPort', $email['Email.SMTPPort'])) ?>" required>
                                <?php if (session('email_errors.SMTPPort')): ?><div class="invalid-feedback"><?= esc(session('email_errors.SMTPPort')) ?></div><?php endif; ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="email-SMTPCrypto"><?= esc(lang('Admin.smtpCrypto')) ?></label>
                                <select id="email-SMTPCrypto" name="SMTPCrypto" class="form-select<?= session('email_errors.SMTPCrypto') ? ' is-invalid' : '' ?>">
                                    <?php foreach (['' => 'smtpNone', 'tls' => 'smtpTls', 'ssl' => 'smtpSsl'] as $value => $label): ?>
                                        <option value="<?= esc($value) ?>" <?= old('SMTPCrypto', $email['Email.SMTPCrypto']) === $value ? 'selected' : '' ?>><?= esc(lang('Admin.' . $label)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (session('email_errors.SMTPCrypto')): ?><div class="invalid-feedback"><?= esc(session('email_errors.SMTPCrypto')) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <p class="form-hint mb-0"><?= esc(lang('Admin.smtpPasswordHint')) ?></p>
                    </div>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.saveEmailSettings')) ?></button></div>
            </form>
            <form method="post" action="<?= route_to('admin/settings/email/test') ?>" class="card mt-3">
                <?= csrf_field() ?>
                <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.sendTestEmail')) ?></h3></div>
                <div class="card-body">
                    <label class="form-label required" for="email-test-recipient"><?= esc(lang('Admin.testRecipient')) ?></label>
                    <input id="email-test-recipient" name="test_email" type="email" class="form-control<?= session('test_email_errors.test_email') ? ' is-invalid' : '' ?>" value="<?= esc(old('test_email', $me->email ?? '')) ?>" maxlength="254" required>
                    <?php if (session('test_email_errors.test_email')): ?><div class="invalid-feedback"><?= esc(session('test_email_errors.test_email')) ?></div><?php endif; ?>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-outline-primary"><span data-submit-idle><i class="ti ti-send me-1" aria-hidden="true"></i><?= esc(lang('Admin.sendTestEmail')) ?></span><span class="d-none" data-submit-loading><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><?= esc(lang('Admin.sendingTestEmail')) ?></span></button></div>
            </form>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        const protocol = document.getElementById('email-protocol');
        const smtpSettings = document.getElementById('email-smtp-settings');
        const updateSmtpSettings = () => {
            const enabled = protocol.value === 'smtp';
            smtpSettings.classList.toggle('d-none', !enabled);
            smtpSettings.querySelectorAll('input, select').forEach((field) => { field.disabled = !enabled; });
        };
        protocol.addEventListener('change', updateSmtpSettings);
        updateSmtpSettings();

    </script>
<?= $this->endSection() ?>