(() => {
    const element = document.getElementById('attachment-preview');
    if (!element || !window.tabler?.Modal) return;

    const modal = window.tabler.Modal.getOrCreateInstance(element);
    const title = element.querySelector('.modal-title');
    const body = element.querySelector('.modal-body');
    const content = element.querySelector('[data-preview-content]');
    const loading = element.querySelector('[data-preview-loading]');
    const error = element.querySelector('[data-preview-error]');
    const download = element.querySelector('[data-preview-download]');
    const open = element.querySelector('[data-preview-open]');
    let trigger = null;
    let timer = null;

    const reset = () => {
        clearTimeout(timer);
        content.replaceChildren();
        loading.classList.add('d-none');
        error.classList.add('d-none');
        body.setAttribute('aria-busy', 'false');
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-attachment-preview]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin) return;

        event.preventDefault();
        reset();
        trigger = link;
        window.tabler.Tooltip?.getInstance(link)?.hide();
        title.textContent = link.dataset.previewName;
        open.href = url.href;
        download.hidden = !link.dataset.previewDownload;
        if (link.dataset.previewDownload) download.href = link.dataset.previewDownload;
        else download.removeAttribute('href');
        loading.classList.remove('d-none');
        body.setAttribute('aria-busy', 'true');

        const isImage = link.dataset.previewMime?.startsWith('image/');
        const frame = document.createElement(isImage ? 'img' : 'iframe');
        frame.title = link.dataset.previewName;
        if (isImage) frame.alt = link.dataset.previewName;
        frame.referrerPolicy = 'no-referrer';
        frame.className = 'w-100 h-100 border-0 d-block invisible' + (isImage ? ' object-fit-contain' : '');
        const failed = () => {
            if (!frame.isConnected) return;
            clearTimeout(timer);
            frame.remove();
            loading.classList.add('d-none');
            error.classList.remove('d-none');
            body.setAttribute('aria-busy', 'false');
        };
        frame.addEventListener('error', failed);
        frame.addEventListener('load', () => {
            if (!frame.isConnected) return;
            try {
                const document = isImage ? null : frame.contentDocument;
                if (document?.URL === 'about:blank') return;
                if ((isImage && frame.naturalWidth === 0) || document?.contentType === 'text/html') {
                    failed();
                    return;
                }
                document?.addEventListener('keydown', (keyEvent) => {
                    if (keyEvent.key === 'Escape') modal.hide();
                });
            } catch {
                failed();
                return;
            }
            clearTimeout(timer);
            loading.classList.add('d-none');
            body.setAttribute('aria-busy', 'false');
            frame.classList.remove('invisible');
        });
        frame.src = url.href;
        content.append(frame);
        timer = setTimeout(failed, 15000);
        modal.show(link);
    });

    element.addEventListener('shown.bs.modal', () => {
        if (trigger) window.tabler.Tooltip?.getInstance(trigger)?.hide();
    });

    element.addEventListener('hide.bs.modal', () => {
        for (const button of element.querySelectorAll('.btn-icon')) {
            window.tabler.Tooltip?.getInstance(button)?.hide();
        }
    });

    element.addEventListener('hidden.bs.modal', () => {
        reset();
        open.removeAttribute('href');
        download.removeAttribute('href');
        if (trigger?.isConnected) trigger.focus();
        trigger = null;
    });
})();