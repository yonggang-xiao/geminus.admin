<ul class="navbar-nav pt-lg-3">
    <li class="nav-item<?= url_is('*/admin/dashboard') ? ' active' : '' ?>">
        <a class="nav-link<?= url_is('*/admin/dashboard') ? ' active' : '' ?>" href="<?= route_to('admin/dashboard') ?>">
            <i class="ti ti-dashboard nav-link-icon icon"></i>
            <span class="nav-link-title"> <?= lang('Admin.dashboard') ?> </span>
        </a>
    </li>
    <?php if (auth()->user()?->can('users.manage-admins')): ?>
        <li class="nav-item<?= url_is('*/admin/users*') ? ' active' : '' ?>">
            <a class="nav-link<?= url_is('*/admin/users*') ? ' active' : '' ?>" href="<?= route_to('admin/users') ?>"<?= url_is('*/admin/users*') ? ' aria-current="page"' : '' ?>>
                <i class="ti ti-users nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"><?= esc(lang('Admin.users')) ?></span>
            </a>
        </li>
    <?php endif; ?>
    <?php if (auth()->user()?->can('admin.settings')): ?>
        <li class="nav-item dropdown<?= url_is('*/admin/settings/*') ? ' active' : '' ?>">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="<?= url_is('*/admin/settings/*') ? 'true' : 'false' ?>">
                <i class="ti ti-settings nav-link-icon icon" aria-hidden="true"></i>
                <span class="nav-link-title"> <?= esc(lang('Admin.systemSettings')) ?> </span>
            </a>
            <div class="dropdown-menu<?= url_is('*/admin/settings/*') ? ' show' : '' ?>">
                <a class="dropdown-item<?= url_is('*/admin/settings/email') ? ' active' : '' ?>" href="<?= route_to('admin/settings/email') ?>"<?= url_is('*/admin/settings/email') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.emailDelivery')) ?></a>
                <a class="dropdown-item<?= url_is('*/admin/settings/email/queue') ? ' active' : '' ?>" href="<?= route_to('admin/settings/email/queue') ?>"<?= url_is('*/admin/settings/email/queue') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.emailQueue')) ?></a>
                <a class="dropdown-item<?= url_is('*/admin/settings/microsoft') ? ' active' : '' ?>" href="<?= route_to('admin/settings/microsoft') ?>"<?= url_is('*/admin/settings/microsoft') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.microsoftLogin')) ?></a>
                <?php if (auth()->user()?->inGroup('superadmin')): ?>
                    <a class="dropdown-item<?= url_is('*/admin/settings/roles') ? ' active' : '' ?>" href="<?= route_to('admin/settings/roles') ?>"<?= url_is('*/admin/settings/roles') ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.roleSettings')) ?></a>
                <?php endif; ?>
            </div>
        </li>
    <?php endif; ?>
</ul>
