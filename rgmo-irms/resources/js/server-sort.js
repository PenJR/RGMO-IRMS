// Keep server-side sorting across all pages, without replacing the surrounding forms.
if (window.fetch && window.AbortController) {
    document.querySelectorAll('[data-server-sort-region]').forEach((region) => {
        const regionName = region.dataset.serverSortRegion;
        const indexUrl = new URL(window.location.href);
        const content = document.querySelector('.main-content');
        const announcement = document.createElement('span');
        announcement.className = 'visually-hidden';
        announcement.setAttribute('role', 'status');
        announcement.setAttribute('aria-live', 'polite');
        region.before(announcement);
        region.style.overflowAnchor = 'none';
        let pendingRequest;
        let reservedHeight = 0;

        const viewportHeightNeeded = () => {
            const top = region.getBoundingClientRect().top;
            const contentScrolls = content && ['auto', 'scroll', 'overlay']
                .includes(window.getComputedStyle(content).overflowY)
                && content.scrollHeight > content.clientHeight;
            return Math.max(0, window.innerHeight - top,
                contentScrolls ? content.getBoundingClientRect().bottom - top : 0);
        };

        const loadSort = async (url, addHistory = true) => {
            pendingRequest?.abort();
            const request = new AbortController();
            pendingRequest = request;
            region.setAttribute('aria-busy', 'true');
            announcement.textContent = 'Sorting…';

            try {
                const response = await fetch(url, {
                    signal: request.signal,
                    headers: { Accept: 'text/html' },
                    credentials: 'same-origin',
                });
                if (!response.ok || response.redirected) throw new Error('The table could not be loaded.');

                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (request.signal.aborted || pendingRequest !== request) return;
                const next = [...page.querySelectorAll('[data-server-sort-region]')]
                    .find((candidate) => candidate.dataset.serverSortRegion === regionName);
                if (!next) throw new Error('The table is unavailable.');

                // Read at render time so scrolling or changing focus during a fetch is respected.
                const position = { x: window.scrollX, y: window.scrollY,
                    contentX: content?.scrollLeft || 0, contentY: content?.scrollTop || 0 };
                const tables = [...region.querySelectorAll('.table-responsive')]
                    .map((wrapper) => ({ x: wrapper.scrollLeft, y: wrapper.scrollTop }));
                const focusedColumn = region.contains(document.activeElement)
                    ? document.activeElement.closest('[data-server-sort]')?.dataset.serverSort : null;

                // A shorter first page must not force the surrounding viewport upward.
                reservedHeight = viewportHeightNeeded();
                region.style.minHeight = `${reservedHeight}px`;
                region.replaceChildren(...next.childNodes);
                window.lucide?.createIcons();

                if (addHistory && url.href !== window.location.href) {
                    window.history.pushState(window.history.state, '', url);
                }
                if (focusedColumn) {
                    [...region.querySelectorAll('[data-server-sort]')]
                        .find((link) => link.dataset.serverSort === focusedColumn)?.focus({ preventScroll: true });
                }
                region.querySelectorAll('.table-responsive').forEach((wrapper, index) => {
                    if (tables[index]) wrapper.scrollTo({ left: tables[index].x, top: tables[index].y, behavior: 'instant' });
                });
                content?.scrollTo({ left: position.contentX, top: position.contentY, behavior: 'instant' });
                window.scrollTo({ left: position.x, top: position.y, behavior: 'instant' });
                const column = url.searchParams.get('sort');
                const label = [...region.querySelectorAll('[data-server-sort]')]
                    .find((link) => link.dataset.serverSort === column)?.firstElementChild?.textContent.trim() || column;
                announcement.textContent = column
                    ? `Sorted by ${label}, ${url.searchParams.get('direction') === 'desc' ? 'descending' : 'ascending'}.`
                    : 'Table updated.';
            } catch (error) {
                if (request.signal.aborted || pendingRequest !== request) return;
                // Keep normal navigation as the fallback for failed requests or an expired session.
                window.location.assign(url);
            } finally {
                if (pendingRequest === request) {
                    region.removeAttribute('aria-busy');
                    pendingRequest = null;
                }
            }
        };

        region.addEventListener('click', (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[data-server-sort]') : null;
            if (!link || event.defaultPrevented || event.button !== 0
                || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
                || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

            const url = new URL(link.href);
            if (url.origin !== indexUrl.origin || url.pathname !== indexUrl.pathname) return;
            // The document's full-page loader sees this click after this handler.
            event.preventDefault();
            void loadSort(url);
        });

        window.addEventListener('popstate', () => {
            if (window.location.pathname === indexUrl.pathname) {
                void loadSort(new URL(window.location.href), false);
            }
        });
        window.addEventListener('pagehide', () => pendingRequest?.abort());

        const releaseExtraSpace = () => {
            if (!reservedHeight) return;
            const needed = viewportHeightNeeded();
            if (needed < reservedHeight) {
                reservedHeight = needed;
                region.style.minHeight = needed ? `${needed}px` : '';
            }
        };
        window.addEventListener('scroll', releaseExtraSpace, { passive: true });
        content?.addEventListener('scroll', releaseExtraSpace, { passive: true });
    });
}
