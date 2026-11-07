const purchaseList = document.querySelector('[data-procurement-list]');

if (purchaseList && window.fetch && window.AbortController) {
    const indexUrl = new URL(purchaseList.dataset.procurementUrl, window.location.href);
    const regions = ['summary', 'orders', 'supplier-history'];
    const announcement = purchaseList.querySelector('[data-procurement-announcement]');
    const content = document.querySelector('.main-content');
    let pendingRequest;
    let reservedHeight = 0;

    const loadStatus = async (url, addHistory = true) => {
        pendingRequest?.abort();
        const request = new AbortController();
        pendingRequest = request;
        purchaseList.setAttribute('aria-busy', 'true');
        announcement.textContent = 'Loading purchase orders…';

        try {
            const response = await fetch(url, {
                signal: request.signal,
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('The purchase list could not be loaded.');

            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (request.signal.aborted) return;
            const replacements = regions.map((region) => {
                const selector = `[data-procurement-${region}]`;
                const current = purchaseList.querySelector(selector);
                const next = page.querySelector(selector);
                if (!current || !next) throw new Error('The purchase list is unavailable.');
                return [current, next];
            });

            // Capture at render time so scrolling during the request is respected.
            const position = { x: window.scrollX, y: window.scrollY, contentY: content?.scrollTop || 0 };
            const focusedStatus = document.activeElement?.getAttribute('data-procurement-status');
            const listTop = purchaseList.getBoundingClientRect().top + position.y;
            // A shorter result set must not clamp the viewport back up the page.
            reservedHeight = Math.max(0, position.y + window.innerHeight - listTop);
            purchaseList.style.minHeight = `${reservedHeight}px`;
            replacements.forEach(([current, next]) => current.replaceChildren(...next.childNodes));

            const status = new URL(url, window.location.href).searchParams.get('status') || '';
            const select = purchaseList.querySelector('#po-status-filter');
            if (select) select.value = status;
            if (addHistory && url.href !== window.location.href) {
                window.history.pushState(window.history.state, '', url);
            }
            if (focusedStatus !== null && focusedStatus !== undefined) {
                purchaseList.querySelectorAll('[data-procurement-status]').forEach((link) => {
                    if (link.dataset.procurementStatus === status) link.focus({ preventScroll: true });
                });
            }
            content?.scrollTo({ top: position.contentY, behavior: 'instant' });
            window.scrollTo({ left: position.x, top: position.y, behavior: 'instant' });
            announcement.textContent = `${status ? status.replaceAll('_', ' ') : 'All'} purchase orders loaded.`;
        } catch (error) {
            if (request.signal.aborted) return;
            // Normal links remain a fallback for network, session, or rendering failures.
            window.location.assign(url);
        } finally {
            if (pendingRequest === request) {
                purchaseList.removeAttribute('aria-busy');
                pendingRequest = null;
            }
        }
    };

    purchaseList.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-procurement-status]');
        if (!link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

        const url = new URL(link.href);
        if (url.origin !== indexUrl.origin || url.pathname !== indexUrl.pathname) return;
        // Runs on the container before the document's full-page loading indicator.
        event.preventDefault();
        void loadStatus(url);
    });

    window.addEventListener('popstate', () => {
        if (window.location.pathname === indexUrl.pathname) {
            void loadStatus(new URL(window.location.href), false);
        }
    });

    // Release extra viewport space as the user scrolls up; never grow it on scrolling down.
    window.addEventListener('scroll', () => {
        if (!reservedHeight) return;
        const listTop = purchaseList.getBoundingClientRect().top + window.scrollY;
        const needed = Math.max(0, window.scrollY + window.innerHeight - listTop);
        if (needed < reservedHeight) {
            reservedHeight = needed;
            purchaseList.style.minHeight = `${needed}px`;
        }
    }, { passive: true });
}
