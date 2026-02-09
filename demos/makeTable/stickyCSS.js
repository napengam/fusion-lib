function makeSticky(idobj, userConfig = null) {


    // ---------------------------------------------------------------------
    // Wildcard handling (id prefix*)
    // ---------------------------------------------------------------------
    if (typeof idobj === 'string' && idobj.endsWith('*')) {
        const prefix = idobj.slice(0, -1);
        document
                .querySelectorAll('table[id^="' + prefix + '"]')
                .forEach(t => makeSticky(t, userConfig));
        return;
    }

    const table = typeof idobj === 'string'
            ? document.getElementById(idobj)
            : idobj;

    if (!table || !table.tHead || !table.tBodies.length) {
        return;
    }

    // ---------------------------------------------------------------------
    // Idempotency / state
    // ---------------------------------------------------------------------
    if (table._stickyState) {
        // reconfigure existing instance
        table._stickyState.configure(userConfig);
        return table._stickyState.api;
    }

    // ---------------------------------------------------------------------
    // Defaults & state
    // ---------------------------------------------------------------------
    const state = {
        config: null,
        styleEl: null,
        resizeObserver: null
    };

    const defaultConfig = {
        col: 0,
        loff: 0,
        toff: 0
    };

    // ---------------------------------------------------------------------
    // Config resolution helpers
    // ---------------------------------------------------------------------
    function resolveOffset(value, dimension) {
        let base = 0;
        let rect = null;

        if (typeof value === 'number') {
            base = value;
        } else if (typeof value === 'string' && value) {
            const el = document.getElementById(value);
            if (el) {
                base = el[dimension];
                rect = el.getBoundingClientRect();
            }
        } else if (value && typeof value === 'object') {
            base = value[dimension];
            rect = value.getBoundingClientRect();
        }

        if (rect) {
            base += dimension === 'clientWidth' ? rect.left : rect.top;
        }

        return base;
    }

    function normalizeConfig(cfg) {
        const merged = Object.assign({}, defaultConfig, cfg || {});
        return {
            col: Math.max(0, merged.col | 0),
            loff: resolveOffset(merged.loff, 'clientWidth'),
            toff: resolveOffset(merged.toff, 'clientHeight')
        };
    }

    // ---------------------------------------------------------------------
    // Core build logic
    // ---------------------------------------------------------------------
    function build() {
        destroyStyle();

        const cfg = state.config;
        const tbody = table.tBodies[0];
        if (!tbody.rows.length)
            return;

        let css = `#${table.id} thead{position:sticky;top:${cfg.toff}px;z-index:4;}`;

        if (cfg.col > 0) {
            let left = cfg.loff;
            const refRow = tbody.rows[0];

            for (let i = 0; i < cfg.col && refRow.cells[i]; i++) {
                const w = refRow.cells[i].getBoundingClientRect().width;

                css +=
                        `#${table.id} tbody td:nth-child(${i + 1}),
                    #${table.id} thead th:nth-child(${i + 1}){
                        position:sticky;
                        left:${left}px;
                        z-index:3;
                    }`;
                left += w;
            }
        }

        table.style.visibility = 'hidden';
        state.styleEl = document.createElement('style');
        state.styleEl.textContent = css;
        document.head.appendChild(state.styleEl);

        cleanupHeader(cfg.col);
        table.style.visibility = '';
    }

    function cleanupHeader(col) {
        if (col <= 0) {
            return;
        }
        Array.from(table.tHead.rows).forEach(row => {
            Array.from(row.cells).forEach(cell => {
                if (
                        cell.style.position === 'sticky' &&
                        (cell.colSpan > col || cell.cellIndex >= col)
                        ) {
                    cell.style.position = 'static';
                }
            });
        });
    }

    // ---------------------------------------------------------------------
    // Teardown helpers
    // ---------------------------------------------------------------------
    function destroyStyle() {
        if (state.styleEl) {
            state.styleEl.remove();
            state.styleEl = null;
        }
    }

    function destroy() {
        destroyStyle();
        if (state.resizeObserver) {
            state.resizeObserver.disconnect();
            state.resizeObserver = null;
        }
        delete table._stickyState;
    }

    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------
    function configure(newConfig) {
        state.config = normalizeConfig(newConfig);
        build();
    }

    const api = {
        rebuild: () => build(),
        configure,
        destroy
    };

    // ---------------------------------------------------------------------
    // Initialization
    // ---------------------------------------------------------------------
    state.config = normalizeConfig(userConfig);
    build();
    table.dataset.stickycols = state.config.col;
    // dynamic reflow
    state.resizeObserver = new ResizeObserver(() => build());
    state.resizeObserver.observe(table);

    table._stickyState = {state, api, configure};
    return api;
}


function makeManySticky(id, config = null) {
    let  tables = document.querySelectorAll(`[id^="${id}"]`);
    tables.forEach(elem => {
        makeSticky(elem, config);
    });
}