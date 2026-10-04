<div class="nav-item dropdown">
    <a href="#" class="p-0 btn btn-icon" data-bs-toggle="dropdown" aria-label="Open language selector">
        <i class="ti ti-language icon"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
    <?php foreach (config('App')->supportedLocales as $locale): ?>
        <form method="post" action="<?= route_to('admin/profile/language') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="language" value="<?= esc($locale) ?>">
            <input type="hidden" name="return" value="<?= esc(service('request')->getUri()->getPath()) ?>">
            <button type="submit" class="dropdown-item <?= service('request')->getLocale() === $locale ? 'active' : '' ?>" <?= service('request')->getLocale() === $locale ? 'aria-current="true"' : '' ?>>
                <?= esc(lang('Admin.localeName', [], $locale)) ?>
            </button>
        </form>
    <?php endforeach ?>
    </div>
</div>
