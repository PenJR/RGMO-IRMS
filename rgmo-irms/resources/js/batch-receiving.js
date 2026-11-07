const initBatchForms = () => {
    const expiryToggle = document.getElementById('has_expiry');
    const trackingToggle = document.getElementById('track_batches');

    if (expiryToggle && trackingToggle) {
        const form = expiryToggle.form;
        const openingStock = form?.querySelector('[name="stock"]');
        const openingBatch = document.getElementById('batch_number');
        const openingExpiry = document.getElementById('expiry_date');
        const syncTracking = () => {
            if (expiryToggle.checked) trackingToggle.checked = true;
            const hasOpeningStock = Number(openingStock?.value || 0) > 0;
            const needsBatch = trackingToggle.checked && hasOpeningStock;
            const needsExpiry = expiryToggle.checked && hasOpeningStock;

            if (openingBatch) {
                document.getElementById('opening_batch_group').hidden = !needsBatch;
                openingBatch.disabled = !needsBatch;
                openingBatch.required = needsBatch;
            }

            if (openingExpiry) {
                document.getElementById('expiry_date_group').hidden = !needsExpiry;
                openingExpiry.disabled = !needsExpiry;
                openingExpiry.required = needsExpiry;
            }
        };

        expiryToggle.addEventListener('change', syncTracking);
        trackingToggle.addEventListener('change', syncTracking);
        openingStock?.addEventListener('input', syncTracking);
        syncTracking();
    }

    document.querySelectorAll('[data-batch-adjustment]').forEach((form) => {
        const type = form.querySelector('[name="adjustment_type"]');
        const batch = form.querySelector('[name="inventory_batch_id"]');
        const number = form.querySelector('[name="batch_number"]');
        const expiry = form.querySelector('[name="expiry_date"]');
        const syncAdjustment = () => {
            const increase = type.value === 'stock_in';
            const newBatch = increase && !batch.value;
            batch.required = !increase && form.dataset.trackBatches === '1';
            form.querySelectorAll('[data-new-adjustment-batch]').forEach((group) => {
                group.hidden = !newBatch;
            });
            number.disabled = !newBatch;
            number.required = newBatch && form.dataset.trackBatches === '1';
            expiry.disabled = !newBatch || form.dataset.hasExpiry !== '1';
            expiry.required = newBatch && form.dataset.hasExpiry === '1';
            expiry.closest('[data-new-adjustment-batch]').hidden = !newBatch || form.dataset.hasExpiry !== '1';
        };

        type.addEventListener('change', syncAdjustment);
        batch.addEventListener('change', syncAdjustment);
        syncAdjustment();
    });

    document.querySelectorAll('[data-receiving-batches]').forEach((group) => {
        const rows = group.querySelector('[data-receiving-batch-rows]');
        const template = group.querySelector('[data-receiving-batch-template]');
        const totalInput = document.getElementById(group.dataset.totalId);
        const message = group.querySelector('[data-batch-total-message]');
        const remaining = Number(totalInput.max);
        const indexes = Array.from(rows.querySelectorAll('[data-batch-quantity]'), (input) => {
            const match = input.name.match(/\[batches\]\[(\d+)\]/);
            return match ? Number(match[1]) : -1;
        });
        let nextIndex = Math.max(-1, ...indexes) + 1;

        const syncTotal = () => {
            let total = 0;
            const quantities = [];

            rows.querySelectorAll('[data-receiving-batch-row]').forEach((row) => {
                const quantity = row.querySelector('[data-batch-quantity]');
                const number = row.querySelector('[data-batch-number]');
                const expiry = row.querySelector('[data-batch-expiry]');
                const amount = Number(quantity.value || 0);
                const active = Number.isFinite(amount) && amount > 0;
                if (active) total += amount;
                number.required = active;
                if (expiry) expiry.required = active && group.dataset.hasExpiry === '1';
                quantity.setCustomValidity('');
                quantities.push(quantity);
            });

            totalInput.value = String(total);
            const exceedsRemaining = total > remaining;
            if (exceedsRemaining && quantities.length) {
                quantities[0].setCustomValidity(`Batch quantities total ${total}; only ${remaining} remain on this purchase order.`);
            }
            message.textContent = exceedsRemaining
                ? `Batch total: ${total}. Reduce quantities to the remaining ${remaining} or fewer.`
                : `Batch total: ${total} of ${remaining} remaining on this purchase order.`;
            message.classList.toggle('text-danger', exceedsRemaining);
            message.classList.toggle('text-muted', !exceedsRemaining);
        };

        group.querySelector('[data-add-receiving-batch]').addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name], [id], [for]').forEach((element) => {
                ['name', 'id', 'for'].forEach((attribute) => {
                    if (element.hasAttribute(attribute)) {
                        element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__INDEX__', String(nextIndex)));
                    }
                });
            });
            nextIndex += 1;
            rows.append(fragment);
            syncTotal();
            rows.lastElementChild.querySelector('[data-batch-number]').focus();
        });

        group.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-remove-receiving-batch]');
            if (!remove) return;
            remove.closest('[data-receiving-batch-row]').remove();
            syncTotal();
        });
        group.addEventListener('input', syncTotal);
        syncTotal();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBatchForms, { once: true });
} else {
    initBatchForms();
}
