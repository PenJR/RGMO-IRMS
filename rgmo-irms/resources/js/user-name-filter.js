document.querySelectorAll('[data-user-name-filter]').forEach((root) => {
    const select = root.querySelector('[data-user-select]');
    const input = root.querySelector('[data-user-search]');
    const list = root.querySelector('[data-user-options]');
    const choices = Array.from(select.options).map((option) => ({
        value: option.value,
        name: option.dataset.name || 'All users',
        email: option.dataset.email || '',
    }));
    let matches = [];
    let active = -1;
    const selected = choices.find((choice) => choice.value === select.value);
    input.value = selected?.value ? selected.name : '';
    select.hidden = true;
    root.querySelector('[data-user-search-wrapper]').hidden = false;
    root.querySelector('[data-user-label]').htmlFor = input.id;

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const choose = (choice) => {
        select.value = choice.value;
        input.value = choice.value ? choice.name : '';
        input.setCustomValidity('');
        close();
    };
    const highlight = () => {
        Array.from(list.querySelectorAll('[role="option"]')).forEach((option, index) => {
            option.setAttribute('aria-selected', String(index === active));
            if (index === active) {
                input.setAttribute('aria-activedescendant', option.id);
                option.scrollIntoView({ block: 'nearest' });
            }
        });
    };
    const open = (query = '') => {
        matches = choices.filter((choice) => !query || `${choice.name} ${choice.email}`.toLocaleLowerCase().includes(query.toLocaleLowerCase()));
        list.replaceChildren();
        active = -1;
        input.removeAttribute('aria-activedescendant');
        matches.forEach((choice, index) => {
            const option = document.createElement('li');
            option.id = `usage-user-option-${index}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            const name = document.createElement('span');
            name.textContent = choice.name;
            option.append(name);
            if (choice.email) {
                const email = document.createElement('small');
                email.textContent = choice.email;
                option.append(email);
            }
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => choose(choice));
            list.append(option);
        });
        if (!matches.length) {
            const empty = document.createElement('li');
            empty.textContent = 'No matching users';
            empty.setAttribute('role', 'status');
            list.append(empty);
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };
    input.addEventListener('focus', () => open());
    input.addEventListener('click', () => { if (list.hidden) open(input.value.trim()); });
    input.addEventListener('input', () => {
        select.value = '';
        input.setCustomValidity(input.value.trim() ? 'Select a user from the dropdown, or clear the name to show all users.' : '');
        open(input.value.trim());
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { close(); return; }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) open(input.value.trim());
            if (!matches.length) return;
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
            highlight();
        } else if (event.key === 'Enter' && !list.hidden && active >= 0) {
            event.preventDefault();
            choose(matches[active]);
        }
    });
    root.addEventListener('focusout', () => {
        window.setTimeout(() => { if (!root.contains(document.activeElement)) close(); }, 0);
    });
    document.addEventListener('click', (event) => { if (!root.contains(event.target)) close(); });
});
