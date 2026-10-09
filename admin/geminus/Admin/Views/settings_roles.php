<?php

?><?= $this->extend('Geminus\Admin\Views\layout_main') ?>

<?= $this->section('head') ?>
    <style>
        @media (max-width: 991.98px) {
            .role-settings-nav { flex-direction: row; overflow-x: auto; }
            .role-settings-nav .list-group-item { flex: 0 0 auto; width: auto; min-width: 9rem; }
        }
    </style>
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <h2 class="page-title"><?= esc($page_title) ?></h2>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <nav class="nav nav-tabs flex-nowrap overflow-auto mb-4" aria-label="<?= esc($page_title, 'attr') ?>">
        <a class="nav-link text-nowrap<?= $view !== 'permissions' ? ' active' : '' ?>" href="<?= route_to('admin/settings/roles') . '?role=' . rawurlencode($selectedRole) ?>"<?= $view !== 'permissions' ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.rolePermissions')) ?></a>
        <a class="nav-link text-nowrap<?= $view === 'permissions' ? ' active' : '' ?>" href="<?= route_to('admin/settings/roles') . '?view=permissions&role=' . rawurlencode($selectedRole) ?>"<?= $view === 'permissions' ? ' aria-current="page"' : '' ?>><?= esc(lang('Admin.permissionCatalog')) ?></a>
    </nav>

    <?php if ($view === 'permissions'): ?>
        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.permissionCatalog')) ?></h3></div>
                    <div class="table-responsive">
                        <table class="table card-table table-vcenter">
                            <thead><tr><th scope="col"><?= esc(lang('Admin.permissionKey')) ?></th><th scope="col"><?= esc(lang('Admin.permissionDescription')) ?></th><th scope="col" class="text-end"><?= esc(lang('Admin.userActions')) ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($permissionGroups as $domain => $items): ?>
                                    <tr class="table-light"><th scope="rowgroup" colspan="3"><?= esc($domain) ?></th></tr>
                                    <?php foreach ($items as $permission => $description): ?>
                                        <tr<?= $editingPermission === $permission ? ' class="table-active"' : '' ?>>
                                            <td class="text-nowrap"><?= esc($permission) ?></td>
                                            <td class="text-secondary"><?= esc($description) ?></td>
                                            <td class="text-end"><a class="btn btn-outline-secondary btn-icon btn-sm" href="<?= route_to('admin/settings/roles') . '?view=permissions&role=' . rawurlencode($selectedRole) . '&permission=' . rawurlencode($permission) ?>" title="<?= esc(lang('Admin.editPermission'), 'attr') ?>" aria-label="<?= esc(lang('Admin.editPermission') . ': ' . $permission, 'attr') ?>"><i class="ti ti-pencil" aria-hidden="true"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4 order-first order-lg-last">
                <form class="card" method="post" action="<?= $editingPermission === null ? route_to('admin/settings/permissions/create') . '?role=' . rawurlencode($selectedRole) : route_to('admin/settings/permissions/update', $editingPermission) . '?role=' . rawurlencode($selectedRole) ?>">
                    <?= csrf_field() ?>
                    <div class="card-header"><h3 class="card-title"><?= esc(lang($editingPermission === null ? 'Admin.createPermission' : 'Admin.editPermission')) ?></h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required" for="permission-name"><?= esc(lang('Admin.permissionKey')) ?></label>
                            <?php if ($editingPermission === null): ?>
                                <input id="permission-name" name="name" class="form-control<?= session('permission_errors.name') ? ' is-invalid' : '' ?>" value="<?= esc(old('name')) ?>" maxlength="80" pattern="[a-z][a-z0-9-]*\.[a-z][a-z0-9-]*" required aria-describedby="permission-name-hint">
                                <?php if (session('permission_errors.name')): ?><div class="invalid-feedback"><?= esc(session('permission_errors.name')) ?></div><?php endif; ?>
                                <div id="permission-name-hint" class="form-text"><?= esc(lang('Admin.permissionKeyHint')) ?></div>
                            <?php else: ?>
                                <input id="permission-name" class="form-control" value="<?= esc($editingPermission, 'attr') ?>" readonly aria-describedby="permission-name-hint">
                                <div id="permission-name-hint" class="form-text"><?= esc(lang('Admin.permissionKeyImmutable')) ?></div>
                            <?php endif; ?>
                        </div>
                        <label class="form-label required" for="permission-description"><?= esc(lang('Admin.permissionDescription')) ?></label>
                        <input id="permission-description" name="description" class="form-control<?= session(($editingPermission === null ? 'permission_errors' : 'permission_edit_errors') . '.description') ? ' is-invalid' : '' ?>" value="<?= esc(old('description', $editingPermission === null ? '' : $permissions[$editingPermission])) ?>" maxlength="255" required>
                        <?php if (session(($editingPermission === null ? 'permission_errors' : 'permission_edit_errors') . '.description')): ?><div class="invalid-feedback"><?= esc(session(($editingPermission === null ? 'permission_errors' : 'permission_edit_errors') . '.description')) ?></div><?php endif; ?>
                    </div>
                    <div class="card-footer btn-list">
                        <button type="submit" class="btn btn-primary"><i class="ti ti-<?= $editingPermission === null ? 'plus' : 'device-floppy' ?> me-1" aria-hidden="true"></i><?= esc(lang($editingPermission === null ? 'Admin.createPermission' : 'Admin.savePermission')) ?></button>
                        <?php if ($editingPermission !== null): ?><a class="btn btn-outline-secondary" href="<?= route_to('admin/settings/roles') . '?view=permissions&role=' . rawurlencode($selectedRole) ?>"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc(lang('Admin.cancel')) ?></a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3 align-items-start">
            <div class="col-12 col-lg-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h3 class="mb-0"><?= esc(lang('Admin.userRole')) ?></h3>
                    <a class="btn btn-outline-primary btn-icon" href="<?= route_to('admin/settings/roles') . '?view=new-role&role=' . rawurlencode($selectedRole) ?>" title="<?= esc(lang('Admin.createRole'), 'attr') ?>" aria-label="<?= esc(lang('Admin.createRole'), 'attr') ?>"><i class="ti ti-plus" aria-hidden="true"></i></a>
                </div>
                <nav class="list-group role-settings-nav" aria-label="<?= esc(lang('Admin.userRole'), 'attr') ?>">
                    <?php foreach ($groups as $name => $group): ?>
                        <a class="list-group-item list-group-item-action<?= in_array($view, ['roles', 'edit-role'], true) && $name === $selectedRole ? ' active' : '' ?>" href="<?= route_to('admin/settings/roles') . '?role=' . rawurlencode($name) ?>"<?= in_array($view, ['roles', 'edit-role'], true) && $name === $selectedRole ? ' aria-current="page"' : '' ?>>
                            <span class="d-block fw-medium"><?= esc($group['title']) ?></span>
                            <span class="d-block small text-secondary"><?= esc($name) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="col-12 col-lg-9">
                <?php if ($view === 'new-role'): ?>
                    <form class="card" method="post" action="<?= route_to('admin/settings/roles/create') ?>">
                        <?= csrf_field() ?>
                        <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.createRole')) ?></h3></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label required" for="role-name"><?= esc(lang('Admin.roleKey')) ?></label>
                                <input id="role-name" name="name" class="form-control<?= session('role_errors.name') ? ' is-invalid' : '' ?>" value="<?= esc(old('name')) ?>" maxlength="40" pattern="[a-z][a-z0-9-]*" required>
                                <?php if (session('role_errors.name')): ?><div class="invalid-feedback"><?= esc(session('role_errors.name')) ?></div><?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required" for="role-title"><?= esc(lang('Admin.roleTitle')) ?></label>
                                <input id="role-title" name="title" class="form-control<?= session('role_errors.title') ? ' is-invalid' : '' ?>" value="<?= esc(old('title')) ?>" maxlength="100" required>
                                <?php if (session('role_errors.title')): ?><div class="invalid-feedback"><?= esc(session('role_errors.title')) ?></div><?php endif; ?>
                            </div>
                            <label class="form-label" for="role-description"><?= esc(lang('Admin.roleDescription')) ?></label>
                            <input id="role-description" name="description" class="form-control<?= session('role_errors.description') ? ' is-invalid' : '' ?>" value="<?= esc(old('description')) ?>" maxlength="255">
                            <?php if (session('role_errors.description')): ?><div class="invalid-feedback"><?= esc(session('role_errors.description')) ?></div><?php endif; ?>
                        </div>
                        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="ti ti-plus me-1" aria-hidden="true"></i><?= esc(lang('Admin.createRole')) ?></button></div>
                    </form>
                <?php elseif ($view === 'edit-role'): ?>
                    <form class="card" method="post" action="<?= route_to('admin/settings/roles/update', $selectedRole) ?>">
                        <?= csrf_field() ?>
                        <div class="card-header"><h3 class="card-title"><?= esc(lang('Admin.editRole')) ?></h3></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label" for="role-name"><?= esc(lang('Admin.roleKey')) ?></label>
                                <input id="role-name" class="form-control" value="<?= esc($selectedRole, 'attr') ?>" readonly aria-describedby="role-name-hint">
                                <div id="role-name-hint" class="form-text"><?= esc(lang('Admin.roleKeyImmutable')) ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required" for="role-title"><?= esc(lang('Admin.roleTitle')) ?></label>
                                <input id="role-title" name="title" class="form-control<?= session('role_edit_errors.title') ? ' is-invalid' : '' ?>" value="<?= esc(old('title', $groups[$selectedRole]['title'])) ?>" maxlength="100" required>
                                <?php if (session('role_edit_errors.title')): ?><div class="invalid-feedback"><?= esc(session('role_edit_errors.title')) ?></div><?php endif; ?>
                            </div>
                            <label class="form-label" for="role-description"><?= esc(lang('Admin.roleDescription')) ?></label>
                            <input id="role-description" name="description" class="form-control<?= session('role_edit_errors.description') ? ' is-invalid' : '' ?>" value="<?= esc(old('description', $groups[$selectedRole]['description'] ?? '')) ?>" maxlength="255">
                            <?php if (session('role_edit_errors.description')): ?><div class="invalid-feedback"><?= esc(session('role_edit_errors.description')) ?></div><?php endif; ?>
                        </div>
                        <div class="card-footer btn-list">
                            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.saveRole')) ?></button>
                            <a class="btn btn-outline-secondary" href="<?= route_to('admin/settings/roles') . '?role=' . rawurlencode($selectedRole) ?>"><i class="ti ti-x me-1" aria-hidden="true"></i><?= esc(lang('Admin.cancel')) ?></a>
                        </div>
                    </form>
                <?php else: ?>
                    <?php if ($protectedRole): ?><div class="card"><?php else: ?><form class="card" method="post" action="<?= route_to('admin/settings/roles/permissions', $selectedRole) ?>" data-role-permissions><?= csrf_field() ?><?php endif; ?>
                        <div class="card-header"><h3 class="card-title"><?= esc($groups[$selectedRole]['title']) ?> <span class="text-secondary small ms-2"><?= esc($selectedRole) ?></span></h3><a class="btn btn-outline-secondary btn-icon btn-sm ms-auto" href="<?= route_to('admin/settings/roles') . '?view=edit-role&role=' . rawurlencode($selectedRole) ?>" title="<?= esc(lang('Admin.editRole'), 'attr') ?>" aria-label="<?= esc(lang('Admin.editRole') . ': ' . $selectedRole, 'attr') ?>"><i class="ti ti-pencil" aria-hidden="true"></i></a></div>
                        <div class="card-body">
                            <?php if ($groups[$selectedRole]['description'] !== ''): ?><p class="text-secondary mb-4"><?= esc($groups[$selectedRole]['description']) ?></p><?php endif; ?>
                            <?php if ($protectedRole): ?>
                                <p class="text-secondary"><?= esc(lang('Admin.superadminPermissionsHint')) ?></p>
                                <p class="text-secondary small"><?= esc(sprintf(lang('Admin.effectivePermissionCount'), count($checked), count($permissions))) ?></p>
                                <?php foreach ($effectivePermissionGroups as $domain => $items): ?>
                                    <section class="mb-4">
                                        <h4 class="h4 border-bottom pb-2 mb-3"><?= esc($domain) ?></h4>
                                        <?php foreach ($items as $permission => $info): ?>
                                            <div class="border-bottom py-2">
                                                <div class="fw-medium"><?= esc($permission) ?></div>
                                                <div class="text-secondary small"><?= esc($info['description']) ?></div>
                                                <div class="text-secondary small"><?= esc(lang('Admin.permissionGrantedBy')) ?>: <?php foreach ($info['grants'] as $grant): ?><code class="me-2"><?= esc($grant) ?></code><?php endforeach; ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </section>
                                <?php endforeach; ?>
                                <?php if ($effectivePermissionGroups === []): ?><p class="text-secondary"><?= esc(lang('Admin.noEffectivePermissions')) ?></p><?php endif; ?>
                                <?php if ($roleGrants !== []): ?>
                                    <details>
                                        <summary class="text-secondary"><?= esc(lang('Admin.rawRoleGrants')) ?></summary>
                                        <ul class="mt-2 mb-0">
                                            <?php foreach ($roleGrants as $grant): ?><li><code><?= esc($grant) ?></code></li><?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php foreach ($permissionGroups as $domain => $items): ?>
                                    <fieldset class="mb-4">
                                        <legend class="h4 border-bottom pb-2 mb-3"><?= esc($domain) ?></legend>
                                        <div class="row g-2">
                                            <?php foreach ($items as $permission => $description): ?>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= esc($permission, 'attr') ?>"<?= isset($checked[$permission]) ? ' checked' : '' ?>>
                                                        <span class="form-check-label"><?= esc($permission) ?></span>
                                                        <span class="form-check-description"><?= esc($description) ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                <?php endforeach; ?>
                                <?php if ($extraGrants !== []): ?>
                                    <fieldset>
                                        <legend class="h4 border-bottom pb-2 mb-3"><?= esc(lang('Admin.extraRoleGrant')) ?></legend>
                                        <?php foreach ($extraGrants as $grant): ?>
                                            <label class="form-check">
                                                <input class="form-check-input" type="checkbox" name="grants[]" value="<?= esc($grant, 'attr') ?>" checked>
                                                <span class="form-check-label"><?= esc($grant) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (! $protectedRole): ?>
                            <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><?= esc(lang('Admin.saveRolePermissions')) ?></button></div>
                        <?php endif; ?>
                    <?php if ($protectedRole): ?></div><?php else: ?></form><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
    <script>
        const rolePermissionsForm = document.querySelector('[data-role-permissions]');
        if (rolePermissionsForm) {
            let permissionsChanged = false;
            rolePermissionsForm.addEventListener('change', () => { permissionsChanged = true; });
            rolePermissionsForm.addEventListener('submit', () => { permissionsChanged = false; });
            window.addEventListener('beforeunload', (event) => {
                if (permissionsChanged) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        }
    </script>
<?= $this->endSection() ?>