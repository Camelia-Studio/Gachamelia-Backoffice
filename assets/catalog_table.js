const tables = new WeakMap();
const collator = new Intl.Collator('fr', { numeric: true, sensitivity: 'base' });
const normalize = (value) => value.trim().toLocaleLowerCase('fr').normalize('NFD').replace(/\p{M}/gu, '');

export function initCatalogTables(root = document) {
    root.querySelectorAll('[data-catalog-scope]').forEach((panel) => {
        if (tables.has(panel)) {
            tables.get(panel)();
            return;
        }
        const table = panel.querySelector('[data-catalog-table]');
        const filters = Array.from(panel.querySelectorAll('[data-filter-column]'));
        const storageKey = `gachamelia.table.${panel.dataset.catalogScope}`;
        let sort = { column: table.dataset.section === 'ranks' ? 'name' : '', direction: 'ascending' };
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            filters.forEach((input) => {
                const value = saved.filters?.[`${input.dataset.filterColumn}:${input.dataset.filterKind}`];
                if (typeof value === 'string') input.value = value;
            });
            if (saved.sort && table.querySelector(`[data-sort-column="${CSS.escape(saved.sort.column || '')}"]`)) {
                sort = { column: saved.sort.column, direction: saved.sort.direction === 'descending' ? 'descending' : 'ascending' };
            }
        } catch { /* Les filtres restent utilisables sans stockage local. */ }

        function value(row, column) {
            return row.querySelector(`td[data-column="${CSS.escape(column)}"]`)?.dataset.filterValue || '';
        }

        function update() {
            const rows = Array.from(table.querySelectorAll('[data-catalog-row]'));
            if (sort.column) {
                rows.sort((left, right) => collator.compare(value(left, sort.column), value(right, sort.column)) * (sort.direction === 'descending' ? -1 : 1));
                rows.forEach((row, index) => {
                    const current = table.tBodies[0].querySelectorAll('[data-catalog-row]')[index];
                    if (current !== row) table.tBodies[0].insertBefore(row, current);
                });
            }
            let count = 0;
            rows.forEach((row) => {
                const matches = filters.every((input) => {
                    if (!input.value) return true;
                    const current = value(row, input.dataset.filterColumn);
                    switch (input.dataset.filterKind) {
                        case 'min': return Number(current) >= Number(input.value);
                        case 'max': return Number(current) <= Number(input.value);
                        case 'exact': return normalize(current) === normalize(input.value);
                        default: return normalize(current).includes(normalize(input.value));
                    }
                });
                row.hidden = !matches;
                if (matches) count++;
            });
            panel.querySelector('[data-catalog-no-match]').hidden = rows.length === 0 || count > 0;
            panel.querySelector('[data-filter-result]').textContent = `${count} / ${rows.length} entrées affichées`;
            table.querySelectorAll('th[data-column]').forEach((th) => th.setAttribute('aria-sort', th.dataset.column === sort.column ? sort.direction : 'none'));
            try {
                localStorage.setItem(storageKey, JSON.stringify({
                    filters: Object.fromEntries(filters.map((input) => [`${input.dataset.filterColumn}:${input.dataset.filterKind}`, input.value])),
                    sort,
                }));
            } catch { /* Le filtrage ne dépend pas de la persistance. */ }
        }
        panel.querySelector('[data-catalog-filters]').addEventListener('submit', (event) => event.preventDefault());
        filters.forEach((input) => input.addEventListener('input', update));
        panel.querySelector('[data-filter-reset]').addEventListener('click', () => {
            filters.forEach((input) => { input.value = ''; });
            update();
        });
        table.querySelectorAll('[data-sort-column]').forEach((button) => button.addEventListener('click', () => {
            sort = { column: button.dataset.sortColumn, direction: sort.column === button.dataset.sortColumn && sort.direction === 'ascending' ? 'descending' : 'ascending' };
            update();
        }));
        tables.set(panel, update);
        update();
    });
}

initCatalogTables();
