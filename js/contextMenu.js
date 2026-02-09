function contextMenuF(id, owner) {
    'use strict';
    let menu;
    let innerMenu;
    let hideTimer = null;
    let currentTarget = null;

    // -------------------------
    // Resolve inner menu
    // -------------------------
    let boundTable = null;


    if (typeof id === 'string' && id !== '') {
        innerMenu = document.getElementById(id);
    } else if (typeof id === 'object' && id !== null) {
        innerMenu = id;
    } else {
        innerMenu = null;
    }


    if (owner instanceof HTMLTableElement) {
        boundTable = owner;
    } else if (typeof owner === 'string') {
        const t = document.getElementById(owner);
        if (t instanceof HTMLTableElement) {
            boundTable = t;
        }
    }

    injectStyle();
    createMenu();

    // -------------------------
    // Style
    // -------------------------
    function injectStyle() {
        if (document.querySelector('style[data-context-style]')) {
            return;
        }

        const style = document.createElement('style');
        style.dataset.contextStyle = '1';
        style.textContent = `
            .contextParent {
                position: fixed;
                background: #ececec;
                border: 1px solid #efefef;
                box-shadow: 0 2px 6px rgba(0,0,0,.25);
                padding: 6px;
                min-width: 160px;
                z-index: 10000;
                font-family: sans-serif;
            }
            .contextParent ul {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .contextParent hr {
                margin: 4px 0;
                border: 0;
                border-top: 1px solid #ccc;
            }
            .liHover {
                cursor: pointer;
                padding: 4px 10px;
                user-select: none;
            }
            .liHover:hover,
            .liHover:focus {
                background: #dcdcdc;
                outline: none;
            }
        `;
        document.head.appendChild(style);
    }

    // -------------------------
    // Menu creation
    // -------------------------
    function createMenu() {
        menu = document.createElement('div');
        menu.className = 'contextParent';
        menu.hidden = true;
        menu.dataset.owner = owner || '';

        if (innerMenu) {
            attachInnerMenu();
        } else {
            createEmptyMenu();
        }

        document.body.appendChild(menu);
        makeFocusable();
    }

    function attachInnerMenu() {
        menu.id = innerMenu.id || `cm-${crypto.randomUUID()}`;
        innerMenu.id = '';
        menu.appendChild(innerMenu);
    }

    function createEmptyMenu() {
        menu.id = `cm-${crypto.randomUUID()}`;
        menu.innerHTML = '<ul></ul>';
        innerMenu = menu.querySelector('ul');
    }

    function makeFocusable() {
        const items = menu.querySelectorAll('li');

        for (const li of items) {
            li.classList.add('liHover');
            li.tabIndex = -1;
        }
    }

    // -------------------------
    // Target resolution
    // -------------------------
    function resolveTarget(event) {
        const td = event.target.closest('td');

        if (!td) {
            return makeGenericTarget(event);
        }

        const tr = td.parentElement;
        const owningTable = tr ? tr.closest('table') : null;

        if (!owningTable) {
            return makeGenericTarget(event);
        }

        if (boundTable && owningTable !== boundTable) {
            return makeGenericTarget(event);
        }

        return {
            type: 'cell',
            table: owningTable,
            row: tr,
            cell: td,
            event: event
        };
    }


    function makeGenericTarget(event) {
        return {
            type: 'generic',
            target: event.target,
            x: event.clientX,
            y: event.clientY,
            event: event
        };
    }


    // -------------------------
    // Menu API
    // -------------------------
    function add(id, icon, label, handler) {
        if (id === '__sep__') {
            addSeparator();
            return;
        }

        const li = document.createElement('li');
        li.className = 'liHover';
        li.tabIndex = -1;
        li.dataset.id = id;
        li.innerHTML = `${icon || ''}${label || ''}`;

        if (typeof handler === 'function') {
            li.addEventListener('click', function (e) {
                e.stopPropagation();
                handler(e);
                close();
            });
        }

        innerMenu.appendChild(li);
    }

    function addSeparator() {
        innerMenu.appendChild(document.createElement('hr'));
    }

    function addMenu(items) {
        for (const item of items) {
            add(item.id, item.icon, item.label, item.handler);
        }
        makeFocusable();
    }

    function remove(id) {
        const el = getEntry(id);
        if (el) {
            el.remove();
        }
    }

    function hide(id) {
        const el = getEntry(id);
        if (el) {
            el.hidden = true;
        }
    }

    function show(id) {
        const el = getEntry(id);
        if (el) {
            el.hidden = false;
        }
    }

    function getEntry(id) {
        return menu.querySelector(`[data-id="${id}"]`);
    }

    // -------------------------
    // Open / Close
    // -------------------------
    function open(event) {
        const resolved = resolveTarget(event);

        event.preventDefault();
        event.stopPropagation();

        currentTarget = resolved;
        menu.target = resolved.cell || resolved.target || null;
        menu.hidden = false;

        const rect = menu.getBoundingClientRect();
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        let left = event.clientX;
        let top = event.clientY;

        if (left + rect.width > vw) {
            left = vw - rect.width - 5;
        }

        if (top + rect.height > vh) {
            top = vh - rect.height - 5;
        }

        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;

        focusFirst();

        menu.onpointerleave = function () {
            hideTimer = setTimeout(close, 250);
        };

        menu.onpointerenter = function () {
            clearTimeout(hideTimer);
        };

        menu.addEventListener('keydown', handleKeyNav);
    }

    function close() {
        menu.hidden = true;
        menu.removeEventListener('keydown', handleKeyNav);
        currentTarget = null;
    }

    function focusFirst() {
        const items = getFocusableItems();
        if (items.length > 0) {
            items[0].focus();
        }
    }

    // -------------------------
    // Keyboard navigation
    // -------------------------
    function getFocusableItems() {
        return Array.from(menu.querySelectorAll('li'))
                .filter(function (el) {
                    return !el.hidden;
                });
    }

    function handleKeyNav(e) {
        const items = getFocusableItems();
        const index = items.indexOf(document.activeElement);

        if (items.length === 0) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            items[(index + 1) % items.length].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            items[(index - 1 + items.length) % items.length].focus();
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            document.activeElement.click();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    }

    // -------------------------
    // Public API
    // -------------------------
    return {
        id: function () {
            return menu.id;
        },
        add: add,
        addMenu: addMenu,
        remove: remove,
        open: open,
        close: close,
        hide: hide,
        show: show,
        getEntry: getEntry
    };
}
