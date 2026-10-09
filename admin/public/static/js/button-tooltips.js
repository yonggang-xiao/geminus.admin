(() => {
    const initialize = () => {
        if (!window.tabler?.Tooltip) {
            return;
        }

        for (const button of document.querySelectorAll('.btn-icon:not([data-bs-toggle]), .btn-close, [data-button-tooltip]')) {
            if (button.getAttribute('data-button-tooltip') === 'false') {
                continue;
            }

            const title = button.getAttribute('title') || button.getAttribute('data-bs-original-title') || button.getAttribute('aria-label');
            if (!title || button.disabled || button.getAttribute('aria-disabled') === 'true' || window.tabler.Tooltip.getInstance(button)) {
                continue;
            }

            window.tabler.Tooltip.getOrCreateInstance(button, {
                title,
                html: false,
                placement: 'auto',
                delay: { show: 50, hide: 50 },
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();