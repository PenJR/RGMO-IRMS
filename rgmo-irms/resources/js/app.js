import './bootstrap';

const loader = document.querySelector('[data-rgmo-loader]');

if (loader) {
    let suppressBeforeUnloadUntil = 0;

    const hideLoader = () => {
        loader.classList.add('is-hidden');
        loader.setAttribute('aria-busy', 'false');
    };

    const showLoader = () => {
        loader.classList.remove('is-hidden');
        loader.setAttribute('aria-busy', 'true');
    };

    const isDownloadUrl = (url) => {
        const pathname = url.pathname.toLowerCase();

        return /\/export-(pdf|csv|excel)(?:\/|$)/.test(pathname)
            || /\.(pdf|csv|xlsx?|zip)(?:$|\?)/.test(pathname);
    };

    if (document.readyState === 'complete') {
        window.requestAnimationFrame(hideLoader);
    } else {
        window.addEventListener('load', hideLoader, { once: true });
    }

    window.addEventListener('pageshow', hideLoader);
    window.addEventListener('beforeunload', () => {
        if (Date.now() < suppressBeforeUnloadUntil) {
            return;
        }

        showLoader();
    });

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
            if (isDownloadUrl(url)) {
                suppressBeforeUnloadUntil = Date.now() + 3000;
                hideLoader();
                return;
            }

            showLoader();
        }
    });
}
