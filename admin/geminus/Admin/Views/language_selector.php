<div class="nav-item dropdown">
    <a href="#" class="p-0 btn btn-icon" data-bs-toggle="dropdown" aria-label="Open language selector">
        <i class="ti ti-language fs-2"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
        <?php foreach (config('App')->supportedLocales as $locale): ?>
        <a href="#" class="dropdown-item <?= service('request')->getLocale() === $locale ? 'active' : '' ?>">
            <?= lang('Admin.localeName', [], $locale) ?>
        </a>
        <?php endforeach ?>
    </div>
</div>
