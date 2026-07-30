import './bootstrap';
import QRCode from 'qrcode';

window.QRCode = QRCode;

const loader = document.querySelector('[data-rgmo-loader]');

if (loader) {
    let suppressBeforeUnloadUntil = 0;

    const hideLoader = () => {
        loader.classList.add('is-hidden');
        loader.setAttribute('aria-hidden', 'true');
    };

    const showLoader = () => {
        loader.classList.remove('is-hidden');
        loader.setAttribute('aria-hidden', 'false');
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

const enhanceDataTable = (table) => {
    const tableBody = table.tBodies[0];
    const responsiveWrapper = table.closest('.table-responsive');

    if (!tableBody || !responsiveWrapper || table.dataset.enhanced === 'true') {
        return;
    }

    table.dataset.enhanced = 'true';
    const rows = Array.from(tableBody.rows);
    const configuredPageSize = Number(table.dataset.pageSize || 10);
    const pageOptions = Array.from(new Set([configuredPageSize, 5, 10, 25]))
        .filter((option) => Number.isInteger(option) && option > 0)
        .sort((left, right) => left - right);
    const label = table.dataset.tableLabel || 'records';
    let pageSize = configuredPageSize > 0 ? configuredPageSize : 10;
    let currentPage = 1;
    let query = '';
    let sortColumn = null;
    let sortDirection = 'ascending';

    const toolbar = document.createElement('div');
    toolbar.className = 'data-table-toolbar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3';

    const searchGroup = document.createElement('div');
    searchGroup.className = 'data-table-search';
    const searchLabel = document.createElement('label');
    const searchId = `table-search-${Math.random().toString(36).slice(2)}`;
    searchLabel.className = 'visually-hidden';
    searchLabel.htmlFor = searchId;
    searchLabel.textContent = `Search ${label}`;
    const searchInput = document.createElement('input');
    searchInput.id = searchId;
    searchInput.type = 'search';
    searchInput.className = 'form-control form-control-sm';
    searchInput.placeholder = table.dataset.searchPlaceholder || `Search ${label}…`;
    searchInput.autocomplete = 'off';
    searchGroup.append(searchLabel, searchInput);

    const toolbarMeta = document.createElement('div');
    toolbarMeta.className = 'd-flex align-items-center gap-3';
    const resultCount = document.createElement('span');
    resultCount.className = 'small text-muted text-nowrap';
    resultCount.setAttribute('aria-live', 'polite');
    const pageSizeLabel = document.createElement('label');
    pageSizeLabel.className = 'd-flex align-items-center gap-2 small text-muted text-nowrap';
    pageSizeLabel.append('Rows');
    const pageSizeSelect = document.createElement('select');
    pageSizeSelect.className = 'form-select form-select-sm data-table-page-size';
    pageOptions.forEach((option) => {
        const optionElement = document.createElement('option');
        optionElement.value = String(option);
        optionElement.textContent = String(option);
        optionElement.selected = option === pageSize;
        pageSizeSelect.appendChild(optionElement);
    });
    pageSizeLabel.appendChild(pageSizeSelect);
    toolbarMeta.append(resultCount, pageSizeLabel);
    toolbar.append(searchGroup, toolbarMeta);
    responsiveWrapper.before(toolbar);

    const pagination = document.createElement('div');
    pagination.className = 'data-table-pagination d-flex align-items-center justify-content-between gap-3';
    const pageLabel = document.createElement('span');
    pageLabel.className = 'small fw-semibold text-muted';
    const pageButtons = document.createElement('div');
    pageButtons.className = 'd-flex gap-2';
    const previousButton = document.createElement('button');
    previousButton.type = 'button';
    previousButton.className = 'btn btn-sm btn-outline-secondary';
    previousButton.textContent = 'Previous';
    const nextButton = document.createElement('button');
    nextButton.type = 'button';
    nextButton.className = 'btn btn-sm btn-outline-secondary';
    nextButton.textContent = 'Next';
    pageButtons.append(previousButton, nextButton);
    pagination.append(pageLabel, pageButtons);
    responsiveWrapper.after(pagination);

    const emptyRow = document.createElement('tr');
    emptyRow.className = 'data-table-empty d-none';
    const emptyCell = document.createElement('td');
    emptyCell.colSpan = Math.max(1, table.tHead?.rows[0]?.cells.length || 1);
    emptyCell.className = 'text-center text-muted py-5';
    emptyCell.textContent = 'No matching records found.';
    emptyRow.appendChild(emptyCell);
    tableBody.appendChild(emptyRow);

    const sortableValue = (row, columnIndex) => {
        const cell = row.cells[columnIndex];
        const value = (cell?.dataset.sortValue || cell?.textContent || '').trim();
        const timestamp = /[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}|\d{4}-\d{2}-\d{2}/.test(value)
            ? Date.parse(value)
            : Number.NaN;

        if (!Number.isNaN(timestamp)) return timestamp;

        const compactValue = value.replace(/[,₱$€£%]/g, '').trim();
        if (/^-?\d+(?:\.\d+)?$/.test(compactValue)) return Number(compactValue);

        return value.toLocaleLowerCase();
    };

    const visibleRows = () => {
        const matches = rows.filter((row) => row.textContent.toLocaleLowerCase().includes(query));

        if (sortColumn !== null) {
            matches.sort((left, right) => {
                const leftValue = sortableValue(left, sortColumn);
                const rightValue = sortableValue(right, sortColumn);
                const comparison = typeof leftValue === 'number' && typeof rightValue === 'number'
                    ? leftValue - rightValue
                    : String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true, sensitivity: 'base' });

                return sortDirection === 'ascending' ? comparison : -comparison;
            });
        }

        return matches;
    };

    const render = () => {
        const matches = visibleRows();
        const pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const firstIndex = (currentPage - 1) * pageSize;
        const lastIndex = Math.min(firstIndex + pageSize, matches.length);
        const pageRows = new Set(matches.slice(firstIndex, lastIndex));

        matches.forEach((row) => tableBody.insertBefore(row, emptyRow));
        rows.forEach((row) => row.classList.toggle('d-none', !pageRows.has(row)));
        emptyRow.classList.toggle('d-none', matches.length !== 0);
        resultCount.textContent = matches.length === 0
            ? `0 ${label}`
            : `Showing ${firstIndex + 1}–${lastIndex} of ${matches.length} ${label}`;
        pageLabel.textContent = `Page ${currentPage} of ${pageCount}`;
        previousButton.disabled = currentPage <= 1;
        nextButton.disabled = currentPage >= pageCount;
        pagination.classList.toggle('d-none', matches.length <= pageSize);
    };

    Array.from(table.tHead?.rows[0]?.cells || []).forEach((header, columnIndex) => {
        if (header.dataset.sortable === 'false' || /actions?/i.test(header.textContent)) return;

        header.classList.add('data-table-sortable');
        header.tabIndex = 0;
        header.setAttribute('role', 'button');
        header.setAttribute('aria-sort', 'none');
        header.title = `Sort by ${header.textContent.trim()}`;

        const sort = () => {
            const isSameColumn = sortColumn === columnIndex;
            sortColumn = columnIndex;
            sortDirection = isSameColumn && sortDirection === 'ascending' ? 'descending' : 'ascending';
            currentPage = 1;
            Array.from(table.tHead.rows[0].cells).forEach((cell) => cell.setAttribute('aria-sort', 'none'));
            header.setAttribute('aria-sort', sortDirection);
            render();
        };

        header.addEventListener('click', sort);
        header.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                sort();
            }
        });
    });

    searchInput.addEventListener('input', () => {
        query = searchInput.value.trim().toLocaleLowerCase();
        currentPage = 1;
        render();
    });
    pageSizeSelect.addEventListener('change', () => {
        pageSize = Number(pageSizeSelect.value);
        currentPage = 1;
        render();
    });
    previousButton.addEventListener('click', () => {
        currentPage -= 1;
        render();
    });
    nextButton.addEventListener('click', () => {
        currentPage += 1;
        render();
    });

    render();
};

document.querySelectorAll('table[data-enhanced-table]').forEach(enhanceDataTable);
