document.addEventListener('DOMContentLoaded', async () => {
    const forms = [...document.querySelectorAll('form[data-notification-target]')];
    const currentForms = [...new Map(forms.filter((form) => new URL(form.dataset.notificationTarget, location.origin).pathname === location.pathname).map((form) => [form.action, form])).values()];

    for (const form of currentForms) {
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) continue;
            const result = await response.json();
            if (result.csrf) {
                document.querySelectorAll('input[type="hidden"]').forEach((input) => {
                    if (input.name === result.csrf.name) input.value = result.csrf.hash;
                });
            }
            const notificationId = form.action;
            forms.filter((candidate) => candidate.action === notificationId).forEach((candidate) => candidate.closest('[data-notification-item]')?.remove());
        } catch (error) {
            console.error('Could not mark notification as read.', error);
        }
    }

    document.querySelectorAll('[data-notifications]').forEach((container) => {
        const count = container.querySelectorAll('[data-notification-item]').length;
        container.querySelector('[data-notification-count]').textContent = count;
        const unreadLabel = container.querySelector('[data-notification-unread-label]');
        if (unreadLabel) unreadLabel.textContent = unreadLabel.dataset.notificationUnreadLabel.replace('{count}', count);
        if (count === 0) {
            container.querySelector('[data-notification-badge]')?.remove();
            unreadLabel?.remove();
            container.querySelector('[data-notification-empty]').classList.remove('d-none');
        }
    });
});