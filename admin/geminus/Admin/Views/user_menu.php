<div class="nav-item dropdown">
    <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
        <span class="avatar"><?= esc(mb_substr($me->username, 0, 2)) ?></span>
        <div class="d-none d-xl-block ps-2">
            <div><?= esc($me->username) ?></div>
        </div>
    </a>
    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
        <div class="d-lg-none">
            <a href="?theme=dark" class="dropdown-item hide-theme-dark"><?= lang('Admin.darkMode') ?></a>
            <a href="?theme=light" class="dropdown-item hide-theme-light"><?= lang('Admin.lightMode') ?></a>
            <div class="dropdown-divider"></div>
        </div>
        <a href="#" class="dropdown-item"><?= lang('Admin.accountSettings') ?></a>
        <a href="<?= route_to('logout') ?>" class="dropdown-item"><?= lang('Admin.logout') ?></a>
    </div>
</div>
