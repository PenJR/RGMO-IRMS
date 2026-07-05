import './bootstrap';

const loader = document.querySelector('[data-rgmo-loader]');

if (loader) {
    const hideLoader = () => {
        loader.classList.add('is-hidden');
        loader.setAttribute('aria-busy', 'false');
    };

    const showLoader = () => {
        loader.classList.remove('is-hidden');
        loader.setAttribute('aria-busy', 'true');
    };

    if (document.readyState === 'complete') {
        window.requestAnimationFrame(hideLoader);
    } else {
        window.addEventListener('load', hideLoader, { once: true });
    }

    window.addEventListener('pageshow', hideLoader);
    window.addEventListener('beforeunload', showLoader);

    document.addEventListener('submit', (event) => {
        if (!event.defaultPrevented) {
            showLoader();
        }
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        if (link.target || link.hasAttribute('download') || link.dataset.noLoader !== undefined) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        const isSamePageHash = url.origin === window.location.origin
            && url.pathname === window.location.pathname
            && url.search === window.location.search
            && url.hash;

        if (url.origin === window.location.origin && !isSamePageHash) {
            showLoader();
        }
    });
}
