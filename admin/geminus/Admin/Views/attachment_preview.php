<div class="modal fade" id="attachment-preview" tabindex="-1" aria-labelledby="attachment-preview-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content h-100">
            <div class="modal-header gap-3">
                <h5 class="modal-title text-break flex-fill" id="attachment-preview-title"><?= esc(lang('Admin.attachmentPreview')) ?></h5>
                <div class="btn-list flex-nowrap flex-shrink-0">
                    <a class="btn btn-sm btn-icon btn-outline-secondary" data-preview-download aria-label="<?= esc(lang('Admin.attachmentDownload'), 'attr') ?>"><i class="ti ti-download" aria-hidden="true"></i></a>
                    <a class="btn btn-sm btn-icon btn-outline-secondary" data-preview-open target="_blank" rel="noopener noreferrer" aria-label="<?= esc(lang('Admin.attachmentOpen'), 'attr') ?>"><i class="ti ti-external-link" aria-hidden="true"></i></a>
                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-bs-dismiss="modal" aria-label="<?= esc(lang('Admin.close'), 'attr') ?>"><i class="ti ti-x" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="modal-body p-0" aria-busy="false">
                <div data-preview-loading class="position-absolute top-50 start-50 translate-middle text-center d-none" role="status">
                    <div class="spinner-border text-secondary" aria-hidden="true"></div>
                    <div class="mt-2 text-secondary"><?= esc(lang('Admin.attachmentLoading')) ?></div>
                </div>
                <div data-preview-error class="h-100 d-flex align-items-center justify-content-center p-3 text-center text-secondary d-none" role="alert"><?= esc(lang('Admin.attachmentPreviewFailed')) ?></div>
                <div data-preview-content class="h-100"></div>
            </div>
        </div>
    </div>
</div>
<script src="/static/js/attachment-preview.js"></script>