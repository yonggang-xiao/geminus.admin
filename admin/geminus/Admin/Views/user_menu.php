<div class="nav-item dropdown">
    <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
        <?= view_cell('Geminus\Admin\Cells\AvatarCell', ['user' => $me]) ?>
        <div class="d-none d-xl-block ps-2">
            <div><?= esc($me->username) ?></div>
        </div>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
        <div class="d-lg-none">
            <a href="?theme=dark" class="dropdown-item hide-theme-dark"><i class="ti ti-moon me-2" aria-hidden="true"></i><?= lang('Admin.darkMode') ?></a>
            <a href="?theme=light" class="dropdown-item hide-theme-light"><i class="ti ti-sun me-2" aria-hidden="true"></i><?= lang('Admin.lightMode') ?></a>
            <div class="dropdown-divider"></div>
            <div class="dropdown-header"><?= esc(lang('Admin.language')) ?></div>
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
            <div class="dropdown-divider"></div>
        </div>
        <a href="<?= route_to('admin/profile') ?>" class="dropdown-item"><i class="ti ti-settings me-2" aria-hidden="true"></i><?= lang('Admin.accountSettings') ?></a>
        <a href="<?= route_to('logout') ?>" class="dropdown-item"><i class="ti ti-logout me-2" aria-hidden="true"></i><?= lang('Admin.logout') ?></a>
    </div>
</div>
