<ul class="navbar-nav pt-lg-3">
    <li class="nav-item<?= url_is('*/admin/dashboard') ? ' active' : '' ?>">
        <a class="nav-link<?= url_is('*/admin/dashboard') ? ' active' : '' ?>" href="<?= route_to('admin/dashboard') ?>">
            <i class="ti ti-dashboard nav-link-icon icon"></i>
            <span class="nav-link-title"> <?= lang('Admin.dashboard') ?> </span>
        </a>
    </li>
    <?php if (auth()->user()?->can('admin.settings')): ?>
        <li class="nav-item dropdown<?= url_is('*/admin/settings/*') ? ' active' : '' ?>">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="<?= url_is('*/admin/settings/*') ? 'true' : 'false' ?>">
                <i class="ti ti-settings nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"> <?= esc(lang('Admin.systemSettings')) ?> </span>
            </a>
            <div class="dropdown-menu<?= url_is('*/admin/settings/*') ? ' show' : '' ?>">
                <a class="dropdown-item<?= url_is('*/admin/settings/email') ? ' active' : '' ?>" href="<?= route_to('admin/settings/email') ?>"<?= url_is('*/admin/settings/email') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.emailDelivery')) ?></a>
                <a class="dropdown-item<?= url_is('*/admin/settings/microsoft') ? ' active' : '' ?>" href="<?= route_to('admin/settings/microsoft') ?>"<?= url_is('*/admin/settings/microsoft') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.microsoftLogin')) ?></a>
            </div>
        </li>
    <?php endif; ?>
</ul>
