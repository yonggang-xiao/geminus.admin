(function () {
    'use strict';

    const defaults = {
        theme: 'light',
        'theme-base': 'gray',
        'theme-font': 'sans-serif',
        'theme-primary': 'blue',
        'theme-radius': '1',
    };

    const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop),
    });

    const safeLocalStorageGet = (key) => {
        try {
            return localStorage.getItem(key);
        } catch {
            return null;
        }
    };

    const safeLocalStorageSet = (key, value) => {
        try {
            localStorage.setItem(key, value);
        } catch {
            // ignore
        }
    };

    const applyConfigKey = (key, value) => {
        const attr = `data-bs-${key}`;
        if (value !== defaults[key]) {
            document.documentElement.setAttribute(attr, value);
        } else {
            document.documentElement.removeAttribute(attr);
        }
    };

    const resolveAndApplyAll = () => {
        for (const key in defaults) {
            const fromUrl = params[key];
            let value;

            if (fromUrl) {
                safeLocalStorageSet(`tabler-${key}`, fromUrl);
                value = fromUrl;
            } else {
                value = safeLocalStorageGet(`tabler-${key}`) || defaults[key];
            }

            applyConfigKey(key, value);
        }
    };

    // Apply as early as possible (script is loaded in <head>)
    resolveAndApplyAll();

    // Support the header toggle buttons/links that use data-set-theme.
    // Keep it delegated so it works for late-rendered partials too.
    document.addEventListener('click', (e) => {
        const target = e.target && e.target.closest ? e.target.closest('[data-set-theme]') : null;
        if (!target) return;

        e.preventDefault();
        const theme = target.getAttribute('data-set-theme');
        if (!theme) return;

        safeLocalStorageSet('tabler-theme', theme);
        applyConfigKey('theme', theme);
    });
})();
