function sortTable(idobj) {
    'use strict';

    // --- Wildcard handling -------------------------------------------------
    if (typeof idobj === 'string' && idobj.endsWith('*')) {
        const prefix = idobj.slice(0, -1);
        const tables = document.querySelectorAll('table[id^="' + prefix + '"]');
        tables.forEach(t => sortTable(t));
        return;
    }

    const table = typeof idobj === 'string' ? document.getElementById(idobj) : idobj;
    if (!table || !table.tHead || !table.tBodies.length) {
        return;
    }

    // --- Idempotency guard -------------------------------------------------
    if (table.dataset.sortInitialized === 'true') {
        return;
    }
    table.dataset.sortInitialized = 'true';

    // --- Defaults ----------------------------------------------------------
    const defaultGetValue = cell => cell ? cell.textContent.trim() : '';
    const defaultCompare = (a, b) => a.localeCompare(b, undefined, {
            numeric: true,
            sensitivity: 'base'
        });

    // --- Per-table state ---------------------------------------------------
    const columnGetters = {};
    const columnComparators = {};
    const sortDirections = {};

    // --- Header discovery --------------------------------------------------
    const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
    const headers = Array.from(headerRow.cells);

    headers.forEach((th, colIndex) => {
        if (th.dataset.sortable === 'false') {
            return;
        }

        sortDirections[colIndex] = 1; // default: ascending

        // --- Type-based comparators ---------------------------------------
        if (th.dataset.type === 'number') {
            columnComparators[colIndex] = (a, b) => {
                const na = Number(a.replace(',', '.'));
                const nb = Number(b.replace(',', '.'));
                if (Number.isNaN(na) && Number.isNaN(nb))
                    return 0;
                if (Number.isNaN(na))
                    return -1;
                if (Number.isNaN(nb))
                    return 1;
                return na - nb;
            };
        }

        if (th.dataset.type === 'gerdate') {
            const parse = s => {
                if (!s)
                    return 0;
                const parts = s.split('.');
                if (parts.length !== 3)
                    return 0;
                const [d, m, y] = parts;
                return new Date(y, m - 1, d).getTime() || 0;
            };

            columnComparators[colIndex] = (a, b) => parse(a) - parse(b);
        }

        // --- UI indicator --------------------------------------------------
        let indicator = th.querySelector('.sort-indicator');
        if (!indicator) {
            indicator = document.createElement('span');
            indicator.className = 'sort-indicator';
            indicator.style.marginLeft = '5px';
            th.appendChild(indicator);
        }

        th.style.cursor = 'pointer';

        th.addEventListener('click', () => {
            sortCore(colIndex, sortDirections[colIndex]);

            headers.forEach((h, i) => {
                const ind = h.querySelector('.sort-indicator');
                if (!ind)
                    return;
                ind.textContent = i === colIndex
                        ? (sortDirections[colIndex] > 0 ? '▲' : '▼')
                        : '';
            });

            sortDirections[colIndex] *= -1;
        });
    });

    // --- Core sorting (stable) --------------------------------------------
    function sortCore(colIndex, direction) {
        const tbody = table.tBodies[0];
        const rows = Array.from(tbody.rows);

        const getValue = columnGetters[colIndex] || defaultGetValue;
        const compare = columnComparators[colIndex] || defaultCompare;

        // decorate rows to enforce stability
        const decorated = rows.map((row, i) => ({
                row,
                index: i,
                value: getValue(row.cells[colIndex])
            }));

        table.style.visibility = 'hidden';

        decorated.sort((a, b) => {
            const result = compare(a.value, b.value) * direction;
            return result !== 0 ? result : a.index - b.index;
        });

        const fragment = document.createDocumentFragment();
        decorated.forEach(item => fragment.appendChild(item.row));
        tbody.appendChild(fragment);

        table.style.visibility = '';
    }

    // --- Extension API -----------------------------------------------------
    return {
        sortCore,
        setGetValue(colIndex, fn) {
            if (typeof fn === 'function') {
                columnGetters[colIndex] = fn;
            }
        },
        setCompareValues(colIndex, fn) {
            if (typeof fn === 'function') {
                columnComparators[colIndex] = fn;
            }
        }
    };
}
