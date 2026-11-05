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

document.querySelectorAll('[data-flash-toast-action]').forEach((action) => {
    const toast = region?.lastElementChild;
    if (!toast) return;
    const link = document.createElement('a');
    link.className = 'rgmo-toast__action';
    link.href = action.href;
    link.textContent = action.textContent.trim();
    toast.querySelector('div')?.append(link);
    action.remove();
});

const dialog = document.querySelector('.rgmo-confirm');
// Keep native constraint validation, but explain allocation limits in our toast.
document.addEventListener('invalid', (event) => {
    const input = event.target;
    if (!input.matches('[data-allocation-limit]') || !input.validity.rangeOverflow || !region) return;
    event.preventDefault();
    if (input.form.querySelector(':invalid') === input) input.focus();
    const message = input.dataset.allocationLimit;
    if (![...region.querySelectorAll('.rgmo-toast p')].some((body) => body.textContent === message)) {
        window.rgmoToast(message, 'warning');
    }
}, true);

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
        const summary = dialog.querySelector('[data-confirm-summary]');
        summary.replaceChildren();
        summary.hidden = true;
        const isWithdrawal = form.hasAttribute('data-withdrawal-summary');
        const symbol = dialog.querySelector('.rgmo-confirm__symbol');
        symbol.classList.toggle('rgmo-confirm__symbol--review', isWithdrawal);
        if (isWithdrawal) {
            symbol.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="3" width="8" height="4" rx="1"/><path d="M8 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M8 12h8M8 16h6"/></svg>';
        } else {
            symbol.textContent = '!';
        }
        dialog.querySelector('#rgmo-confirm-title').textContent = isWithdrawal ? 'Review withdrawal' : 'Confirm action';
        dialog.querySelector('[data-confirm-cancel]').textContent = isWithdrawal ? 'Go back & edit' : 'Cancel';
        if (isWithdrawal) {
            const lines = [...form.querySelectorAll('[data-resource-name]')].filter((input) => Number(input.value) > 0);
            if (!lines.length) {
                window.rgmoToast('Enter a quantity greater than 0 for at least one resource to withdraw.', 'warning');
                form.querySelector('[data-resource-name]')?.focus();
                return;
            }
            const details = document.createElement('dl');
            const addDetail = (label, value) => {
                const term = document.createElement('dt');
                const description = document.createElement('dd');
                term.textContent = label;
                description.textContent = value;
                details.append(term, description);
            };
            addDetail('Project / Request', `${form.dataset.project} / ${form.dataset.request}`);
            addDetail('Withdrawal date', form.elements.namedItem('withdrawn_on').value);
            addDetail('Received by', form.elements.namedItem('received_by').value);
            const heading = document.createElement('h3');
            heading.textContent = 'Resources to withdraw';
            const list = document.createElement('ul');
            for (const input of lines) {
                const line = document.createElement('li');
                const name = document.createElement('strong');
                name.textContent = input.dataset.resourceName;
                const quantity = document.createElement('span');
                const remaining = Number(input.max) - Number(input.value);
                quantity.textContent = `Selected quantity: ${input.value}`;
                const balance = document.createElement('span');
                balance.className = 'rgmo-confirm__balance';
                balance.textContent = `Remaining allocation: ${remaining}`;
                line.append(name, quantity, balance);
                list.append(line);
            }
            const notes = form.elements.namedItem('notes').value.trim();
            if (notes) addDetail('Notes', notes);
            summary.append(details, heading, list);
            summary.hidden = false;
        }
        pending = { form, submitter: event.submitter };
        dialog.querySelector('#rgmo-confirm-message').textContent = trigger.dataset.confirm;
        dialog.querySelector('[data-confirm-accept]').textContent = isWithdrawal ? 'Confirm withdrawal' : (event.submitter?.textContent.trim() || 'Confirm');
        dialog.showModal();
    }, true);
}
