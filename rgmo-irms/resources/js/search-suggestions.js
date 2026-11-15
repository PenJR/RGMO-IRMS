let suggestionId = 0;

const normalise = (value) => value.replace(/\s+/g, ' ').trim();

const isActionCell = (cell) => {
    const header = cell.closest('table')?.tHead?.rows[0]?.cells[cell.cellIndex];
    return /actions?/i.test(header?.textContent || '') || Boolean(cell.querySelector('button, form'));
};

const cellValues = (cell) => {
    if (isActionCell(cell)) return [];

    return (cell.innerText || cell.textContent || '')
    .split('\n')
    .map(normalise)
    .filter((value) => value.length > 1 && value.length <= 140);
};

const pageValues = () => Array.from(document.querySelectorAll('table tbody tr'))
    .filter((row) => !row.classList.contains('data-table-empty'))
    .flatMap((row) => Array.from(row.cells).flatMap(cellValues));

export const attachSearchSuggestions = (input, values = pageValues) => {
    if (!input || input.dataset.suggestionsReady === 'true' || input.closest('[data-procurement-search]')) return;

    input.dataset.suggestionsReady = 'true';
    input.autocomplete = 'off';
    input.setAttribute('aria-autocomplete', 'list');
    const container = input.closest('.input-group') || input.parentElement;
    if (!container) return;
    container.classList.add('position-relative');

    const list = document.createElement('div');
    list.id = `search-suggestions-${++suggestionId}`;
    list.className = 'list-group position-absolute start-0 end-0 mt-1 shadow-sm d-none';
    list.style.zIndex = '1060';
    list.setAttribute('role', 'listbox');
    container.appendChild(list);
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-expanded', 'false');

    const close = () => {
        list.replaceChildren();
        list.classList.add('d-none');
        input.setAttribute('aria-expanded', 'false');
    };
    const show = () => {
        const query = normalise(input.value).toLocaleLowerCase();
        if (query.length < 1) return close();
        const matches = [...new Set(values())]
            .filter((value) => value.toLocaleLowerCase().startsWith(query))
            .slice(0, 8);
        close();
        matches.forEach((value) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action text-start';
            option.setAttribute('role', 'option');
            option.textContent = value;
            option.addEventListener('click', () => {
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                close();
                input.focus();
            });
            list.appendChild(option);
        });
        if (matches.length) {
            list.classList.remove('d-none');
            input.setAttribute('aria-expanded', 'true');
        }
    };

    let timer;
    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(show, 300);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });
    document.addEventListener('click', (event) => {
        if (!container.contains(event.target)) close();
    });
};

document.querySelectorAll('input[name="search"], input[placeholder*="earch" i]').forEach((input) => attachSearchSuggestions(input));
