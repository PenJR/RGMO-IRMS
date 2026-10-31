const region = document.querySelector('.rgmo-toasts');

window.rgmoToast = (message, type = 'info') => {
    if (!region) return;
    const titles = { success: 'Success', warning: 'Attention', error: 'Something went wrong', info: 'Notice' };
    if (!Object.hasOwn(titles, type)) type = 'info';
    const toast = document.createElement('div');
    toast.className = `rgmo-toast rgmo-toast--${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-atomic', 'true');
    const content = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = titles[type];
    const body = document.createElement('p');
    body.textContent = message;
    content.append(title, body);
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'rgmo-toast__close';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '×';
    toast.append(content, close);
    region.append(toast);
    let timer;
    const pause = () => window.clearTimeout(timer);
    const dismiss = () => { pause(); toast.remove(); };
    const resume = () => {
        pause();
        if (type === 'success' || type === 'info') timer = window.setTimeout(dismiss, 7000);
    };
    close.addEventListener('click', dismiss);
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', resume);
    resume();
};

document.querySelectorAll('[data-flash-toast]').forEach((flash) => {
    window.rgmoToast(flash.textContent.trim(), flash.dataset.flashToast);
    flash.remove();
});

const dialog = document.querySelector('.rgmo-confirm');
if (dialog) {
    let pending = null;
    let approvedForm = null;
    const cancel = () => dialog.close();
    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', cancel);
    dialog.addEventListener('close', () => { pending = null; });
    dialog.querySelector('[data-confirm-accept]').addEventListener('click', () => {
        if (!pending) return;
        const { form, submitter } = pending;
        pending = null;
        dialog.close();
        approvedForm = form;
        try {
            form.requestSubmit(submitter || undefined);
        } finally {
            approvedForm = null;
        }
    });
    // Capture before the page loader so canceling never starts navigation.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const trigger = event.submitter?.matches('[data-confirm]')
            ? event.submitter : form;
        if (!trigger.dataset.confirm || approvedForm === form) return;
        event.preventDefault();
        if (dialog.open) return;
        pending = { form, submitter: event.submitter };
        dialog.querySelector('#rgmo-confirm-message').textContent = trigger.dataset.confirm;
        dialog.querySelector('[data-confirm-accept]').textContent = event.submitter?.textContent.trim() || 'Confirm';
        dialog.showModal();
    }, true);
}
