<ul class="navbar-nav pt-lg-3">
    <li class="nav-item<?= url_is('*/admin/dashboard') ? ' active' : '' ?>">
        <a class="nav-link<?= url_is('*/admin/dashboard') ? ' active' : '' ?>" href="<?= route_to('admin/dashboard') ?>">
            <i class="ti ti-dashboard nav-link-icon icon"></i>
            <span class="nav-link-title"> <?= lang('Admin.dashboard') ?> </span>
        </a>
    </li>
    <?php if (auth()->user()?->can('admin.settings')): ?>
        <li class="nav-item<?= url_is('*/admin/settings/*') ? ' active' : '' ?>">
            <a class="nav-link<?= url_is('*/admin/settings/*') ? ' active' : '' ?>" href="<?= route_to('admin/settings/email') ?>">
                <i class="ti ti-settings nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"> <?= esc(lang('Admin.systemSettings')) ?> </span>
            </a>
        </li>
    <?php endif; ?>
</ul>
