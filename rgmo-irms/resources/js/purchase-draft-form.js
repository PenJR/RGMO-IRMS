import { PurchaseDraftAutosaver } from './purchase-draft-autosaver';

function initializePurchaseDraftForm() {
    const form = document.querySelector('[data-po-draft-form]');
    if (!form) return;

    const modal = form.closest('.modal');
    const rows = form.querySelector('#po-items tbody');
    const rowTemplate = rows.firstElementChild.cloneNode(true);
    const status = form.querySelector('[data-po-autosave-status]');
    const actions = form.querySelector('[data-po-autosave-actions]');
    const receiptInput = form.querySelector('[name="receipt"]');
    const receiptArea = form.querySelector('[data-po-saved-receipt]');
    const receiptLink = form.querySelector('[data-po-receipt-link]');
    const receiptWarning = form.querySelector('[data-po-receipt-warning]');
    const recoveryBody = document.querySelector('[data-po-drafts-body]');
    const baseUrl = form.dataset.draftsUrl.replace(/\/$/, '');
    const field = (name) => form.elements.namedItem(name);
    const defaultOrderDate = field('order_date').defaultValue;
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    let saver = null;
    let savedReceipt = null;
    let removeReceipt = false;
    let rejectedReceipt = null;
    let submitting = false;
    let navigating = false;
    let loading = false;
    let sessionExpired = false;
    const approvedSubmits = new WeakSet();

    const showModal = () => window.bootstrap?.Modal.getOrCreateInstance(modal).show();
    const notify = (message, type = 'error') => window.rgmoToast?.(message, type);
    const newId = () => globalThis.crypto.randomUUID?.() || '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (value) =>
        (Number(value) ^ globalThis.crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> Number(value) / 4).toString(16));

    function setStatus(message, state = 'idle') {
        status.textContent = message;
        status.className = `small mb-1 ${state === 'error' || state === 'conflict' ? 'text-danger' : 'text-muted'}`;
        actions.classList.toggle('d-none', !['error', 'conflict'].includes(state));
        form.querySelector('[data-po-autosave-retry]').classList.toggle('d-none', state === 'conflict');
        form.querySelector('[data-po-autosave-reload]').classList.toggle('d-none', state !== 'conflict');
        form.querySelector('[data-po-autosave-copy]').classList.toggle('d-none', state !== 'conflict');
    }

    async function request(url, options = {}) {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), options.body instanceof FormData ? 90000 : 20000);
        let response;
        try {
            response = await fetch(url, {
                credentials: 'same-origin', ...options, signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    ...options.headers,
                },
            });
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('The save timed out. Retry when your connection is available.');
            throw error;
        } finally { window.clearTimeout(timeout); }
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = Object.values(data.errors || {}).flat()[0] || data.message || 'The unfinished entry could not be saved.';
            const error = Object.assign(new Error(message), { status: response.status, draft: data.draft, errors: data.errors });
            if ([401, 403, 419].includes(response.status)) {
                sessionExpired = true;
                error.message = 'Your session or access has changed. Sign in again before saving; your current entries remain in this tab.';
            }
            throw error;
        }
        return data;
    }

    async function saveSnapshot(snapshot, { keepalive }) {
        const { id, expected_revision: revision, payload, receipt, removeReceipt: removing } = snapshot;
        if (receipt) {
            const body = new FormData();
            body.set('expected_revision', revision);
            body.set('remove_receipt', removing ? '1' : '0');
            for (const [key, value] of Object.entries(payload)) {
                if (key === 'items') {
                    value.forEach((row, index) => Object.entries(row).forEach(([name, entry]) => body.set(`payload[items][${index}][${name}]`, entry ?? '')));
                } else body.set(`payload[${key}]`, value ?? '');
            }
            body.set('receipt', receipt);
            // Large uploads cannot use the browser's limited keepalive budget.
            return request(`${baseUrl}/${id}`, { method: 'POST', body });
        }
        return request(`${baseUrl}/${id}`, {
            method: 'POST', keepalive,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ expected_revision: revision, payload, remove_receipt: removing }),
        });
    }

    function renderReceipt() {
        const selected = receiptInput.files[0];
        const visible = selected || (!removeReceipt && savedReceipt);
        receiptArea.classList.toggle('d-none', !visible);
        if (selected) {
            receiptLink.removeAttribute('href');
            receiptLink.textContent = `${selected.name} (not saved yet)`;
        } else if (savedReceipt && !removeReceipt) {
            receiptLink.textContent = savedReceipt.name;
            if (savedReceipt.available !== false) receiptLink.href = savedReceipt.url;
            else {
                receiptLink.removeAttribute('href');
                receiptWarning.textContent = 'The saved receipt is unavailable. Attach it again before saving the purchase.';
                receiptInput.setCustomValidity('Attach the missing receipt again or remove it before saving the purchase.');
            }
        }
    }

    function updateRecoveryRow(draft) {
        if (!recoveryBody || draft.status !== 'active') return;
        let row = [...recoveryBody.querySelectorAll('[data-po-draft-row]')].find((entry) => entry.dataset.poDraftRow === draft.id);
        if (!row) {
            row = document.createElement('tr');
            row.dataset.poDraftRow = draft.id;
            const name = document.createElement('td'); name.dataset.poDraftName = '';
            const time = document.createElement('td'); time.dataset.poDraftTime = '';
            const buttons = document.createElement('td'); buttons.className = 'text-end';
            for (const [attribute, label, style] of [['poResume', 'Continue', 'primary'], ['poDiscard', 'Discard', 'danger']]) {
                const button = document.createElement('button');
                button.type = 'button'; button.className = `btn btn-sm btn-outline-${style} ms-1`;
                button.dataset[attribute] = draft.id; button.textContent = label; buttons.append(button);
            }
            row.append(name, time, buttons); recoveryBody.prepend(row);
        }
        recoveryBody.querySelector('[data-po-drafts-empty]')?.remove();
        row.dataset.revision = draft.revision;
        row.querySelector('[data-po-draft-name]').textContent = draft.payload?.po_number || 'Untitled purchase';
        row.querySelector('[data-po-draft-time]').textContent = new Date(draft.updated_at).toLocaleString();
    }

    function bindSaver(id = newId(), revision = 0) {
        id = id.toLowerCase();
        saver?.stop();
        sessionExpired = false;
        field('procurement_draft_id').value = id;
        field('procurement_draft_revision').value = revision || '';
        const instance = new PurchaseDraftAutosaver({ id, revision, save: saveSnapshot, onState: (state, details) => {
            if (saver !== instance) return;
            if (state === 'saving') setStatus('Saving unfinished entries…');
            if (state === 'saved') {
                sessionExpired = false;
                field('procurement_draft_revision').value = details.draft.revision;
                savedReceipt = details.draft.receipt;
                if (details.snapshot.receipt && receiptInput.files[0] === details.snapshot.receipt) receiptInput.value = '';
                if (details.snapshot.removeReceipt && removeReceipt) removeReceipt = false;
                renderReceipt(); updateRecoveryRow(details.draft);
                setStatus(details.dirty ? 'Saving latest changes…' : `Entries saved at ${new Date(details.draft.updated_at).toLocaleTimeString()}.`);
            }
            if (state === 'conflict') {
                setStatus('Another tab changed or finished this entry. Your current fields are still here. Load the saved version or keep these entries as a new draft.', 'conflict');
            }
            if (state === 'error') {
                setStatus(`Not saved: ${details.error.message} Your entries remain in this tab.`, 'error');
                if (details.snapshot.receipt && details.error.errors?.receipt) {
                    rejectedReceipt = details.snapshot.receipt;
                    receiptWarning.textContent = details.error.errors.receipt[0];
                    receiptInput.setCustomValidity(receiptWarning.textContent);
                    instance.update(readSnapshot()); // Preserve the fields even when a receipt is invalid.
                }
            }
        } });
        saver = instance;
        return instance;
    }

    function readSnapshot() {
        return {
            payload: {
                po_number: field('po_number').value,
                supplier_id: field('supplier_id').value || null,
                order_date: field('order_date').value,
                expected_delivery_date: field('expected_delivery_date').value,
                funding_source_id: field('funding_source_id').value || null,
                notes: field('notes').value,
                items: [...rows.querySelectorAll('.po-item-row')].map((row) => ({
                    inventory_item_id: row.querySelector('.po-item-select').value || null,
                    ordered_quantity: row.querySelector('.po-quantity').value,
                    unit_of_measurement: row.querySelector('.po-unit').value,
                    unit_cost: row.querySelector('.po-cost').value,
                })),
            },
            receipt: receiptInput.files[0] && receiptInput.files[0] !== rejectedReceipt ? receiptInput.files[0] : null,
            removeReceipt,
        };
    }

    function recalculate() {
        let total = 0;
        const anyEntry = [...rows.children].some((row) => [...row.querySelectorAll('input, select')].some((input) => input.value !== ''));
        [...rows.children].forEach((row, index) => {
            const hasEntry = [...row.querySelectorAll('input, select')].some((input) => input.value !== '');
            row.querySelectorAll('input, select').forEach((input) => { input.required = hasEntry || (!anyEntry && index === 0); });
            row.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`); });
            const subtotal = (parseFloat(row.querySelector('.po-quantity').value) || 0) * (parseFloat(row.querySelector('.po-cost').value) || 0);
            total += Number.isFinite(subtotal) ? subtotal : 0;
            row.querySelector('.po-subtotal').textContent = money.format(Number.isFinite(subtotal) ? subtotal : 0);
        });
        form.querySelector('#po-total').textContent = money.format(total);
        form.querySelector('#add-po-item').disabled = rows.children.length >= 100;
    }

    function setSelect(select, value) {
        select.querySelectorAll('[data-unavailable]').forEach((option) => option.remove());
        const string = value == null ? '' : String(value);
        if (string && ![...select.options].some((option) => option.value === string)) {
            const filter = document.querySelector(select.name === 'supplier_id' ? '#po-supplier-filter' : select.name === 'funding_source_id' ? '#po-funding-filter' : '#po-item-filter');
            const label = [...(filter?.options || [])].find((option) => option.value === string)?.textContent || `Selection ${string}`;
            const option = new Option(`${label} — unavailable; choose another`, string);
            option.dataset.unavailable = ''; select.add(option);
        }
        select.value = string;
        select.setCustomValidity(select.selectedOptions[0]?.hasAttribute('data-unavailable') ? 'This selection is no longer available. Choose another before saving the purchase.' : '');
    }

    function restorePayload(payload = {}) {
        for (const name of ['po_number', 'order_date', 'expected_delivery_date', 'notes']) field(name).value = payload[name] ?? '';
        for (const name of ['supplier_id', 'funding_source_id']) setSelect(field(name), payload[name]);
        rows.replaceChildren();
        const entries = Array.isArray(payload.items) && payload.items.length ? payload.items.slice(0, 100) : [{}];
        for (const entry of entries) {
            const item = entry && typeof entry === 'object' ? entry : {};
            const row = rowTemplate.cloneNode(true);
            row.querySelectorAll('input, select').forEach((input) => { input.value = ''; input.setCustomValidity(''); input.classList.remove('is-invalid'); });
            setSelect(row.querySelector('.po-item-select'), item.inventory_item_id);
            for (const [selector, key] of [['.po-quantity', 'ordered_quantity'], ['.po-unit', 'unit_of_measurement'], ['.po-cost', 'unit_cost']]) {
                row.querySelector(selector).value = item[key] ?? '';
            }
            rows.append(row);
        }
        recalculate(); window.lucide?.createIcons();
    }

    function markChanged() {
        if (loading || submitting) return;
        if ((saver || bindSaver()).update(readSnapshot())) setStatus('Changes waiting to save…');
    }

    async function flush(options) {
        if (saver) return saver.flush(options);
        return null;
    }

    function resetForm() {
        saver?.stop(); saver = null; savedReceipt = null; removeReceipt = false; rejectedReceipt = null;
        form.reset(); receiptInput.value = ''; receiptInput.setCustomValidity(''); receiptWarning.textContent = '';
        field('procurement_draft_id').value = ''; field('procurement_draft_revision').value = '';
        restorePayload({ order_date: defaultOrderDate, items: [{}] }); renderReceipt();
        setStatus('Entries save automatically as you type. Save draft creates the purchase order.');
    }

    async function loadDraft(id) {
        if (loading) return;
        loading = true;
        const enabled = [...form.elements].filter((input) => !input.disabled);
        enabled.forEach((input) => { input.disabled = true; });
        try {
            const draft = await request(`${baseUrl}/${id}`);
            if (draft.status !== 'active') {
                if (draft.completed_procurement_id) {
                    navigating = true;
                    window.location.assign(`${form.action.replace(/\/$/, '')}/${draft.completed_procurement_id}`);
                } else {
                    [...recoveryBody.querySelectorAll('[data-po-draft-row]')].find((row) => row.dataset.poDraftRow === id)?.remove();
                    if (saver?.id === id) {
                        setStatus('This entry was discarded. Keep the current fields as a new draft to continue.', 'conflict');
                        form.querySelector('[data-po-autosave-reload]').classList.add('d-none');
                    }
                    notify('This unfinished entry was discarded.');
                }
                return;
            }
            restorePayload(draft.payload); receiptInput.value = ''; receiptInput.setCustomValidity('');
            rejectedReceipt = null; removeReceipt = false; receiptWarning.textContent = ''; savedReceipt = draft.receipt;
            bindSaver(draft.id, draft.revision); renderReceipt(); updateRecoveryRow(draft);
            setStatus(`Restored entries saved at ${new Date(draft.updated_at).toLocaleString()}.`); showModal();
        } catch (error) {
            setStatus(error.message, saver?.conflicted ? 'conflict' : 'error'); notify(error.message);
        } finally { enabled.forEach((input) => { input.disabled = false; }); loading = false; }
    }

    form.addEventListener('input', (event) => {
        if (event.target.type !== 'file') { recalculate(); markChanged(); }
    });
    form.addEventListener('change', (event) => {
        const input = event.target;
        if (input.matches('.po-item-select')) input.closest('.po-item-row').querySelector('.po-unit').value = input.selectedOptions[0]?.dataset.unit || '';
        if (input.tagName === 'SELECT') input.setCustomValidity(input.selectedOptions[0]?.hasAttribute('data-unavailable') ? 'Choose an available selection.' : '');
        if (input === receiptInput) {
            rejectedReceipt = null; receiptWarning.textContent = ''; input.setCustomValidity(''); removeReceipt = false;
            const file = input.files[0];
            if (file && (file.size > 5 * 1024 * 1024 || !/\.(pdf|jpe?g|png)$/i.test(file.name))) {
                rejectedReceipt = file;
                receiptWarning.textContent = 'Choose a PDF, JPG or PNG up to 5 MB. The other entries will still be saved.';
                input.setCustomValidity(receiptWarning.textContent);
            }
            renderReceipt();
        }
        recalculate(); markChanged();
    });
    form.querySelector('#add-po-item').addEventListener('click', () => {
        if (rows.children.length >= 100) return;
        const row = rowTemplate.cloneNode(true);
        row.querySelectorAll('input, select').forEach((input) => { input.value = ''; input.setCustomValidity(''); });
        rows.append(row); recalculate(); window.lucide?.createIcons(); markChanged();
    });
    rows.addEventListener('click', (event) => {
        if (!event.target.closest('.remove-po-item') || rows.children.length <= 1) return;
        event.target.closest('.po-item-row').remove(); recalculate(); markChanged();
    });
    form.querySelector('[data-po-remove-receipt]').addEventListener('click', () => {
        receiptInput.value = ''; receiptInput.setCustomValidity(''); rejectedReceipt = null;
        removeReceipt = true; receiptWarning.textContent = ''; renderReceipt(); markChanged();
    });

    document.querySelector('[data-bs-target="#newPurchaseOrderModal"]')?.addEventListener('click', async (event) => {
        event.preventDefault(); event.stopPropagation();
        if (loading || submitting) return;
        try { await flush(); resetForm(); showModal(); }
        catch { showModal(); }
    });
    document.querySelector('[data-po-unfinished]')?.addEventListener('click', async (event) => {
        const resume = event.target.closest('[data-po-resume]');
        const discard = event.target.closest('[data-po-discard]');
        if ((!resume && !discard) || loading || submitting) return;
        const id = (resume || discard).dataset[resume ? 'poResume' : 'poDiscard'];
        if (resume) {
            if (saver?.id === id && (saver.dirty || saver.busy || saver.conflicted)) { showModal(); return; }
            try { await flush(); await loadDraft(id); } catch { showModal(); }
            return;
        }
        if (!window.confirm('Discard this unfinished entry and its saved receipt? This cannot be undone.')) return;
        discard.disabled = true;
        try {
            const row = discard.closest('[data-po-draft-row]');
            await request(`${baseUrl}/${id}`, {
                method: 'DELETE', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ expected_revision: saver?.id === id ? saver.revision : Number(row.dataset.revision) }),
            });
            if (saver?.id === id) resetForm();
            row.remove(); notify('Unfinished entry discarded.', 'success');
        } catch (error) {
            if (error.draft) updateRecoveryRow(error.draft);
            notify(error.message);
        } finally { discard.disabled = false; }
    });

    form.querySelector('[data-po-autosave-retry]').addEventListener('click', () => { void flush().catch(() => {}); });
    form.querySelector('[data-po-autosave-reload]').addEventListener('click', () => {
        if (!window.confirm('Replace the fields in this tab with the saved version? Unsaved changes in this tab will be replaced.')) return;
        void loadDraft(saver.id);
    });
    form.querySelector('[data-po-autosave-copy]').addEventListener('click', () => {
        const hasSavedReceipt = savedReceipt && !receiptInput.files[0];
        savedReceipt = null; removeReceipt = false;
        if (!receiptInput.files[0]) receiptInput.setCustomValidity('');
        bindSaver();
        if (hasSavedReceipt) receiptWarning.textContent = 'These entries are a new copy. Attach the receipt again if it is needed.';
        renderReceipt(); markChanged();
    });

    form.addEventListener('submit', async (event) => {
        if (approvedSubmits.has(form)) return;
        event.preventDefault();
        if (submitting) return;
        submitting = true;
        const button = event.submitter;
        const snapshot = readSnapshot();
        const enabled = [...form.elements].filter((input) => !input.disabled);
        enabled.forEach((input) => { input.disabled = true; });
        try {
            // Serialize again to include autofill or programmatic changes.
            (saver || bindSaver()).update(snapshot);
            await flush();
            enabled.forEach((input) => { input.disabled = false; });
            if (!form.reportValidity()) { submitting = false; return; }
            saver.stop(); approvedSubmits.add(form); navigating = true;
            form.requestSubmit(button || undefined);
        } catch (error) {
            submitting = false; enabled.forEach((input) => { input.disabled = false; });
            notify(`Purchase not created. ${error.message}`);
        }
    }, true);

    // Finish acknowledged saves before normal in-app navigation or logout.
    document.addEventListener('click', async (event) => {
        const link = event.target.closest('a[href]');
        if (!saver?.dirty || !link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.hasAttribute('data-bs-toggle')) return;
        const destination = new URL(link.href, window.location.href);
        if (!['http:', 'https:'].includes(destination.protocol) || (destination.pathname === window.location.pathname && destination.search === window.location.search)) return;
        event.preventDefault(); event.stopPropagation();
        try { await flush(); navigating = true; window.location.assign(destination.href); }
        catch { showModal(); }
    }, true);
    document.addEventListener('submit', async (event) => {
        const other = event.target;
        if (other === form || !saver?.dirty || event.defaultPrevented || approvedSubmits.has(other)) return;
        event.preventDefault(); event.stopPropagation();
        try {
            await flush(); approvedSubmits.add(other);
            try { other.requestSubmit(event.submitter || undefined); }
            finally { approvedSubmits.delete(other); }
        }
        catch { showModal(); }
    }, true);
    modal.addEventListener('hide.bs.modal', () => { if (!submitting && saver?.dirty) void flush().catch((error) => notify(error.message)); });
    window.addEventListener('beforeunload', (event) => {
        if (navigating || !saver?.dirty) return;
        void flush({ keepalive: true }).catch(() => {});
        event.preventDefault(); event.returnValue = '';
    });
    window.addEventListener('pagehide', () => { if (!navigating && saver?.dirty) void flush({ keepalive: true }).catch(() => {}); });
    document.addEventListener('visibilitychange', () => { if (document.hidden && saver?.dirty) void flush({ keepalive: true }).catch(() => {}); });
    window.addEventListener('online', () => { if (saver?.dirty && !saver.conflicted) void flush().catch(() => {}); });
    // Continuous typing still reaches the server without waiting for a pause.
    window.setInterval(() => { if (saver?.dirty && !saver.busy && !saver.conflicted && !sessionExpired) void flush().catch(() => {}); }, 5000);

    recalculate();
    if (form.dataset.restoreOld === 'true') {
        try {
            restorePayload(JSON.parse(form.querySelector('[data-po-old-input]')?.textContent || '{}'));
        } catch { /* Keep the server-rendered fields if old-input metadata is unavailable. */ }
        const id = field('procurement_draft_id').value;
        if (id) {
            const oldRevision = Number(field('procurement_draft_revision').value) || 0;
            const restoringSaver = bindSaver(id, oldRevision);
            void request(`${baseUrl}/${id}`).then((draft) => {
                if (saver !== restoringSaver) return;
                if (restoringSaver.revision !== oldRevision && draft.revision <= restoringSaver.revision) return;
                savedReceipt = draft.receipt; renderReceipt();
                if (draft.revision !== restoringSaver.revision || draft.status !== 'active') {
                    saver.update(readSnapshot());
                    void flush().catch(() => {});
                } else markChanged();
            }).catch((error) => { if (saver === restoringSaver) setStatus(error.message, restoringSaver.conflicted ? 'conflict' : 'error'); });
        } else markChanged();
        showModal();
    }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializePurchaseDraftForm, { once: true });
else initializePurchaseDraftForm();
