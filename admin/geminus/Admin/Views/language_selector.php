<div class="nav-item dropdown">
    <a href="#" class="p-0 btn btn-icon" data-bs-toggle="dropdown" data-button-tooltip="false" aria-label="<?= esc(lang('Admin.language'), 'attr') ?>">
        <i class="ti ti-language icon" aria-hidden="true"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
    <?php foreach (config('App')->supportedLocales as $locale): ?>
        <form method="post" action="<?= route_to('admin/profile/language') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="language" value="<?= esc($locale) ?>">
            <input type="hidden" name="return" value="<?= esc(service('request')->getUri()->getPath()) ?>">
            <button type="submit" class="dropdown-item <?= service('request')->getLocale() === $locale ? 'active' : '' ?>" <?= service('request')->getLocale() === $locale ? 'aria-current="true"' : '' ?>>
                <i class="ti ti-language me-2" aria-hidden="true"></i>
                <?= esc(lang('Admin.localeName', [], $locale)) ?>
            </button>
        </form>
    <?php endforeach ?>
    </div>
</div>
