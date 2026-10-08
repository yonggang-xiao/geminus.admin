    <?php

use Geminus\Admin\Libraries\MailTemplates;

?><?= $this->extend('Geminus\Admin\Views\layout_main') ?>

    <?= $this->section('header') ?>
        <h2 class="page-title"><?= esc($page_title) ?></h2>
    <?= $this->endSection() ?>

    <?= $this->section('content') ?>
        <nav aria-label="<?= esc($page_title, 'attr') ?>">
            <ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto">
                <?php foreach ($types as $option => $definition): ?>
                    <li class="nav-item"><a class="nav-link text-nowrap<?= $type === $option ? ' active' : '' ?>" href="<?= route_to('admin/mail/templates') ?>?type=<?= esc($option, 'url') ?>&locale=<?= esc($locale, 'url') ?>"<?= $type === $option ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.' . $definition['label'])) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <nav aria-label="<?= esc(lang('Admin.language'), 'attr') ?>">
            <ul class="nav nav-pills mb-3">
                <?php foreach (config('App')->supportedLocales as $option): ?>
                    <li class="nav-item"><a class="nav-link<?= $locale === $option ? ' active' : '' ?>" href="<?= route_to('admin/mail/templates') ?>?type=<?= esc($type, 'url') ?>&locale=<?= esc($option, 'url') ?>"<?= $locale === $option ? ' aria-current="page"' : '' ?>><?= esc($option) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="card">
            <form method="post" action="<?= route_to('admin/mail/templates/update', $type, $locale) ?>">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required" for="template-subject"><?= esc(lang('Admin.mailSubject')) ?></label>
                        <input class="form-control<?= session('template_errors.subject') ? ' is-invalid' : '' ?>" id="template-subject" name="subject" maxlength="255" required aria-describedby="template-subject-variables" value="<?= esc(old('subject', $template['subject']), 'attr') ?>">
                        <?php if (session('template_errors.subject')): ?><div class="invalid-feedback"><?= esc(session('template_errors.subject')) ?></div><?php endif; ?>
                        <div class="form-hint" id="template-subject-variables"><?= esc(lang('Admin.mailSubjectTokens')) ?>: <?= esc(implode(', ', array_map(static fn (string $name): string => '{' . $name . '}', $types[$type]['subjectVariables']))) ?></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12<?= $types[$type]['html'] ? ' col-lg-6' : '' ?>">
                            <label class="form-label required" for="template-body"><?= esc(lang($types[$type]['html'] ? 'Admin.mailHtmlBody' : 'Admin.mailTemplateBody')) ?></label>
                            <textarea class="form-control<?= session('template_errors.body') ? ' is-invalid' : '' ?>" id="template-body" name="body" rows="15" maxlength="10000" required aria-describedby="template-variables"><?= esc(old('body', $template['body'])) ?></textarea>
                            <?php if (session('template_errors.body')): ?><div class="invalid-feedback"><?= esc(session('template_errors.body')) ?></div><?php endif; ?>
                            <div class="form-hint" id="template-variables"><?= esc(lang('Admin.mailTemplateVariables')) ?>: <?= esc(implode(', ', array_map(static fn (string $name): string => '{' . $name . '}', $types[$type]['variables']))) ?></div>
                        </div>
                        <?php if ($types[$type]['html']): ?>
                            <div class="col-12 col-lg-6">
                                <label class="form-label" for="template-preview"><?= esc(lang('Admin.mailPreview')) ?></label>
                                <iframe id="template-preview" title="<?= esc(lang('Admin.mailPreview'), 'attr') ?>" class="w-100 border rounded" style="height: 360px" sandbox="" referrerpolicy="no-referrer"></iframe>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.mailTemplateSave')) ?></button>
                </div>
            </form>
            <form method="post" action="<?= route_to('admin/mail/templates/reset', $type, $locale) ?>" class="card-footer">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary"><?= esc(lang('Admin.mailTemplateRestore')) ?></button>
            </form>
        </div>
    <?= $this->endSection() ?>

    <?php if ($types[$type]['html']): ?>
        <?= $this->section('javascript') ?>
            <script>
                const bodyInput = document.getElementById('template-body');
                const preview = document.getElementById('template-preview');
                const samples = { username: 'Example User', link: <?= json_encode(match ($type) {
                    'invitation' => url_to('magic-link', $locale),
                    'magic-link' => url_to('verify-magic-link', $locale) . '?token=preview',
                    default      => '',
                }, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, code: '123456', ipAddress: '192.0.2.1', userAgent: 'Browser', date: '2026-01-01', microsoftLogin: <?= json_encode((new MailTemplates())->microsoftLoginBody($locale), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> };
                const updatePreview = () => {
                    const markup = bodyInput.value.replace(/\{([a-zA-Z][a-zA-Z0-9]*)\}/g, (token, name) => Object.hasOwn(samples, name) ? samples[name] : token);
                    preview.srcdoc = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:">' + markup;
                };
                bodyInput.addEventListener('input', updatePreview);
                updatePreview();
            </script>
        <?= $this->endSection() ?>
    <?php endif; ?>