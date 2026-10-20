const clock = document.getElementById('headerClock');

if (clock) {
    const zone = 'Asia/Manila';
    const timeFormat = new Intl.DateTimeFormat('en-PH', { timeZone: zone, hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
    const dateFormat = new Intl.DateTimeFormat('en-PH', { timeZone: zone, weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    const dayFormat = new Intl.DateTimeFormat('en-CA', { timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit' });
    const todayParts = () => {
        const parts = Object.fromEntries(dayFormat.formatToParts(new Date()).map(part => [part.type, part.value]));
        return { year: Number(parts.year), month: Number(parts.month) - 1, day: Number(parts.day) };
    };
    let today = todayParts();
    let month = today.month;
    let year = today.year;
    const render = () => {
        clock.querySelector('[data-calendar-month]').textContent = new Intl.DateTimeFormat('en-PH', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(year, month, 1)));
        const grid = clock.querySelector('[data-calendar-grid]');
        grid.replaceChildren();
        for (const label of ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']) {
            const cell = document.createElement('span');
            cell.className = 'header-calendar-weekday';
            cell.textContent = label;
            grid.append(cell);
        }
        const offset = new Date(Date.UTC(year, month, 1)).getUTCDay();
        for (let index = 0; index < offset; index++) grid.append(document.createElement('span'));
        const days = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        for (let day = 1; day <= days; day++) {
            const cell = document.createElement('span');
            cell.textContent = day;
            if (day === today.day && month === today.month && year === today.year) {
                cell.className = 'header-calendar-today';
                cell.setAttribute('aria-current', 'date');
            }
            grid.append(cell);
        }
    };
    const update = () => {
        const now = new Date();
        const time = clock.querySelector('[data-header-time]');
        time.textContent = timeFormat.format(now);
        time.dateTime = now.toISOString();
        clock.querySelector('[data-header-date]').textContent = dateFormat.format(now);
        const current = todayParts();
        if (current.day !== today.day || current.month !== today.month || current.year !== today.year) {
            today = current;
            render();
        }
    };
    const move = delta => {
        const date = new Date(Date.UTC(year, month + delta, 1));
        year = date.getUTCFullYear();
        month = date.getUTCMonth();
        render();
    };
    clock.querySelector('[data-calendar-prev]').addEventListener('click', () => move(-1));
    clock.querySelector('[data-calendar-next]').addEventListener('click', () => move(1));
    clock.querySelector('[data-calendar-today]').addEventListener('click', () => {
        today = todayParts();
        year = today.year;
        month = today.month;
        render();
    });
    document.addEventListener('click', event => { if (!clock.contains(event.target)) clock.open = false; });
    clock.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            clock.open = false;
            clock.querySelector('summary').focus();
        }
    });
    update();
    render();
    setInterval(update, 1000);
}
