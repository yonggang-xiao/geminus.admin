<?php if ($uploadUrl !== ''): ?>
    <form method="post" action="<?= esc($uploadUrl) ?>" enctype="multipart/form-data" class="card-body border-bottom">
        <?= csrf_field() ?>
        <label class="form-label required" for="<?= esc($inputId) ?>"><?= esc($labels['file']) ?></label>
        <div class="row g-2 align-items-start">
            <div class="col-12 col-md">
                <input type="file" name="file" id="<?= esc($inputId) ?>" class="form-control<?= $error !== '' ? ' is-invalid' : '' ?>" accept="<?= esc($accept) ?>" required aria-describedby="<?= esc($inputId) ?>-hint<?= $error !== '' ? ' ' . esc($inputId) . '-error' : '' ?>"<?= $error !== '' ? ' aria-invalid="true"' : '' ?>>
                <?php if ($error !== ''): ?><div class="invalid-feedback" id="<?= esc($inputId) ?>-error"><?= esc($error) ?></div><?php endif; ?>
                <div class="form-text" id="<?= esc($inputId) ?>-hint"><?= esc($hint) ?></div>
            </div>
            <div class="col-12 col-md-auto"><button type="submit" class="btn btn-primary"><i class="ti ti-upload me-1" aria-hidden="true"></i><?= esc($labels['upload']) ?></button></div>
        </div>
    </form>
<?php endif; ?>
<div class="table-responsive">
    <table class="table card-table table-vcenter">
        <thead><tr><th scope="col"><?= esc($labels['file']) ?></th><th scope="col" class="w-25"><?= esc($labels['size']) ?></th><th scope="col" class="w-25 text-end"><?= esc($labels['actions']) ?></th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td class="text-nowrap" data-label="<?= esc($labels['file'], 'attr') ?>"><?= esc($item['name']) ?></td>
                    <td class="text-nowrap" data-label="<?= esc($labels['size'], 'attr') ?>"><?= esc($item['size']) ?> KB</td>
                    <td data-label="<?= esc($labels['actions'], 'attr') ?>"><div class="btn-list align-items-start justify-content-end flex-nowrap">
                        <?php if ($item['previewUrl'] !== ''): ?>
                            <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= esc($item['previewUrl']) ?>" target="_blank" rel="noopener noreferrer" data-attachment-preview data-preview-name="<?= esc($item['name'], 'attr') ?>" data-preview-mime="<?= esc($item['mime'], 'attr') ?>" data-preview-download="<?= esc($item['downloadUrl'], 'attr') ?>" aria-label="<?= esc($labels['preview'] ?? lang('Admin.attachmentPreview'), 'attr') ?>"><i class="ti ti-eye" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if ($item['downloadUrl'] !== ''): ?>
                            <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= esc($item['downloadUrl']) ?>" aria-label="<?= esc($labels['download'], 'attr') ?>"><i class="ti ti-download" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if ($item['removeUrl'] !== ''): ?>
                            <form method="post" action="<?= esc($item['removeUrl']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?= esc($labels['remove'], 'attr') ?>"><i class="ti ti-trash" aria-hidden="true"></i></button>
                            </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?><tr><td colspan="3"><?= view_cell('Geminus\Admin\Cells\EmptyStateCell', ['message' => $labels['empty']]) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>