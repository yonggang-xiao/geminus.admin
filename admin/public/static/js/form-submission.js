(() => {
    let submitting = false;

    const submitButtons = () => Array.from(document.forms)
        .filter((form) => form.method.toLowerCase() === 'post')
        .flatMap((form) => Array.from(form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"], input[type="image"]')));

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') {
            return;
        }
        if (submitting) {
            event.preventDefault();
            return;
        }

        submitting = true;
        const button = event.submitter || form.querySelector('button[type="submit"], button:not([type]), input[type="submit"], input[type="image"]');
        const loading = button?.querySelector('[data-submit-loading]');
        if (loading) {
            event.preventDefault();
            button.querySelector('[data-submit-idle]').classList.add('d-none');
            loading.classList.remove('d-none');
            button.setAttribute('aria-busy', 'true');
            submitButtons().forEach((item) => { item.disabled = true; });
            window.setTimeout(() => { form.submit(); }, 100);
            return;
        }

        if (button) {
            button.classList.add('btn-loading');
            button.setAttribute('aria-busy', 'true');
        }
        window.setTimeout(() => {
            submitButtons().forEach((item) => { item.disabled = true; });
        }, 0);
    });

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }
        submitting = false;
        submitButtons().forEach((button) => {
            button.disabled = false;
            button.classList.remove('btn-loading');
            button.removeAttribute('aria-busy');
            button.querySelector('[data-submit-idle]')?.classList.remove('d-none');
            button.querySelector('[data-submit-loading]')?.classList.add('d-none');
        });
    });
})();