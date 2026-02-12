function tableEdit(idx) {
    'use strict';
    // =======================================
    // Internal state
    // =======================================
    let ri, ci, nexttd;
    let theTable = '';
    let id = '';
    let jump, cm;
    let cellDictionary = null;
    let validatorCallBack = null;
    let changeCallBack = null;
    let errorCallBack = () => {
    };
    let confirmCallBack = () => {
    };
    // =======================================
    // Table lookup
    // =======================================
    if (typeof idx === 'string') {
        theTable = document.getElementById(idx);
        id = idx;
    } else if (typeof idx === 'object') {
        theTable = idx;
        id = theTable.id;
    }

    if (!theTable || !id) {
        return;
    }

// =======================================
// Context-menu setup (optional)
// =======================================
    if (typeof contextMenuF === 'function') {
        cm = new contextMenuF('', id);
        cm.addMenu([
            {id: 'insert', icon: '➕', label: ' insert row', handler: contextAddRow},
            {id: 'copy', icon: '⧉', label: ' copy row', handler: contextCopyRow},
            {id: 'delete', icon: '🗑️', label: ' delete row…', handler: contextDeleteRow}
        ]);
    }

// =======================================
// Delegated Event Binding
// =======================================
    theTable.addEventListener('contextmenu', handleContextMenu);
    theTable.addEventListener('click', cellEdit);
    theTable.addEventListener('keydown', (e) => {
        if (e.target.matches('.theField')) {
            jump.updown(e);
        }
    });
    theTable.dataset.ininput = '';
    jump = jumper(theTable);
    // =======================================
    // Core Handlers
    // =======================================
    function handleContextMenu(e) {
        if (!e.target.closest('td')) {
            return;
        }

        if (theTable.dataset.ininput) {
            [ri, ci] = rici();
            const cell = theTable.rows[ri].cells[ci];
            cell.innerHTML = cell.dataset.oldvalue;
            theTable.dataset.ininput = '';
            cell.removeAttribute('data-editcursor');
        }

        if (cm) {
            cm.open(e);
        }
    }

    function cancelEdit() {
        const field = theTable.querySelector('.theField');
        if (field) {
            const td = field.closest('td');
            td.innerHTML = td.dataset.oldvalue;
            td.removeAttribute('data-editcursor');
            theTable.dataset.ininput = '';
        }
    }

    function cellEdit(e) {
        const td = getTD(e.target);
        if (!td) {
            return;
        }
        nexttd = td;
        if (jump.escapeEdit()) {
            [ri, ci] = rici();
            const cell = theTable.rows[ri].cells[ci];
            cell.innerHTML = cell.dataset.oldvalue;
            cell.removeAttribute('data-editcursor');
            theTable.dataset.ininput = '';
            return;
        }

        if (td.querySelector('.theField')) {
            return; // already editing
        }

        if (theTable.dataset.ininput === '') {
            if (!theTable.querySelector('.theField')) {
                nextCell(td);
            }
            return;
        }

        processInputChange(td);
    }

    function processInputChange(td) {
        theTable.dataset.ininput = '';
        [ri, ci] = rici();
        const cell = theTable.rows[ri].cells[ci];
        cell.style.backgroundColor = '';
        const field = theTable.querySelector('.theField');
        const value = field ? field.value : '';
        if (cell.dataset.oldvalue === value) {
            cell.removeAttribute('data-editcursor');
            cell.innerHTML = htmlentity(cell.dataset.oldvalue);
            nextCell(td);
            return;
        }

        if (cellDictionary && validatorCallBack) {
            const vcb = validatorCallBack(value, cellDictionary[ci]);
            if (!vcb.ok) {

                cell.removeAttribute('data-editcursor');
                cell.innerHTML = htmlentity(cell.dataset.oldvalue);
                cell.style.backgroundColor = 'silver';
                jump.clickHere(ri, ci);

                errorCallBack(vcb.msg);
                return;
            }
        }

        if (typeof changeCallBack === 'function') {
            changeCallBack({task: 'update', value}, responds);
            return;
        }

        cell.innerHTML = htmlentity(value);
        nextCell(td);
    }

    function responds(resPkg) {
        const cell = theTable.querySelector('[data-editcursor]');
        if (!cell) {
            return;
        }

        cell.removeAttribute('data-editcursor');
        if (resPkg.error) {
            errorCallBack(resPkg.error);
            cell.innerHTML = htmlentity(cell.dataset.oldvalue);
            cell.style.backgroundColor = 'silver';
            jump.clickHere(ri, ci);
        } else {
            cell.innerHTML = htmlentity(resPkg.result);
            nextCell(nexttd);
        }

        cell.removeAttribute('data-oldvalue');
        const f = theTable.querySelector('.theField');
        if (f) {
            f.focus();
        }
    }

    // =======================================
    // Editing Helpers
    // =======================================
    function nextCell(td) {
        const opt = cellDictionary ? cellDictionary[td.cellIndex] : {};
        if (opt?.skip === 'yes' && jump.fakeClick()) {
            jump.clickJump(0);
            return;
        }
        jump.fakeClick();
        const wcs = window.getComputedStyle(td);
        const mnw = ['paddingLeft', 'paddingRight', 'borderLeftWidth', 'borderRightWidth']
                .map(k => parseFloat(wcs[k]) || 0).reduce((a, b) => a + b, 0);
        const mnh = ['paddingTop', 'paddingBottom', 'borderTopWidth', 'borderBottomWidth']
                .map(k => parseFloat(wcs[k]) || 0).reduce((a, b) => a + b, 0);
        const s = `
            outline:none;border:0;padding:0;
            max-width:${td.clientWidth - mnw - 2}px;
            height:${td.clientHeight - mnh}px;
        `;
        const readonly = opt?.edit === 'no' ? 'readonly' : '';
        td.dataset.oldvalue = td.innerHTML.trim();
        if (opt?.type === 'select' && Array.isArray(opt.options)) {
            const optionsHtml = opt.options.map(o => {
                let value, text;
                if (typeof o === 'string' && o.includes('|')) {
                    [value, text] = o.split('|');
                } else {
                    value = o;
                    text = o;
                }
                const selected = (value === td.dataset.oldvalue) ? 'selected' : '';
                return `<option value="${htmlentity(value)}" ${selected}>${htmlentity(text)}</option>`;
            }).join('');
            td.innerHTML = `<select class="theField" style="${s}">${optionsHtml}</select>`;
        } else {
            td.innerHTML = `<input ${readonly}  class="theField"
                style="${s}" type="text" value="${htmlentity(td.dataset.oldvalue)}">`;
            if (opt?.type === 'date') {
                td.firstChild.onclick = getCalendar;
            }
        }
        td.firstChild.oncontextmenu = () => {
            window.event.stopPropagation();
        };

        td.dataset.editcursor = '';
        theTable.dataset.ininput = '1';
        td.querySelector('.theField').focus();
    }
    function getCalendar(e) {
        var y = '', m = '', v;
        e.stopPropagation();
        v = this.value.split('.');
        if (v.length === 3) {
            y = v[2];
            m = v[1];
        }
        this.id = 'hier';
        window.calendar.fetchCalendar(m, y, this.id);
    }
    // =======================================
    // Context-menu Handlers
    // =======================================
    function contextAddRow(e) {
        const here = walkUp(e.target);
        if (!here) {
            return null;
        }
        const newRow = here.table.insertRow(here.row.rowIndex);
        [...here.row.cells].forEach(() => newRow.insertCell());
        jump.refresh();
        return newRow.rowIndex;
    }

    function contextDeleteRow(e) {
        confirmCallBack('Delete row?', () => {
            const here = walkUp(e.target);
            if (here) {
                here.table.deleteRow(here.row.rowIndex);
                jump.refresh();
            }
        }, () => {
        });
    }

    function contextCopyRow(e) {
        const here = walkUp(e.target);
        if (!here) {
            return null;
        }
        const newRow = here.table.insertRow(here.row.rowIndex);
        [...here.row.cells].forEach((cell, i) => {
            newRow.insertCell();
            newRow.cells[i].innerHTML = htmlentity(cell.textContent || '');
        });
        jump.refresh();
        return newRow.rowIndex;
    }

    // =======================================
    // Utilities
    // =======================================
    function getTD(obj) {
        while (obj && obj.tagName !== 'BODY') {
            if (obj.tagName === 'TD' && obj.closest('table').id === id) {
                ri = obj.parentNode.rowIndex;
                ci = obj.cellIndex;
                return obj;
            }
            obj = obj.parentNode;
        }
        return null;
    }

    function rici() {
        const td = theTable.querySelector('.theField').parentNode;
        return [td.parentNode.rowIndex, td.cellIndex];
    }

    function htmlentity(value) {
        if (typeof value !== 'string') {
            return value;
        }
        return value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
    }

    function walkUp(src) {
        const contextMenu = src.closest('.contextParent');
        if (!contextMenu) {
            return null;
        }
        const tabId = contextMenu.dataset.owner;
        const obj = contextMenu.target;
        return {col: obj, row: obj.parentNode, table: obj.closest(`#${tabId}`)};
    }

    // =======================================
    // Public API
    // =======================================
    return {
        setChangeCallBack: (func) => (changeCallBack = func),
        setErrorCallBack: (func) => (errorCallBack = func),
        setConfirmCallBack: (func) => (confirmCallBack = func),
        setDictionary: (dict) => (cellDictionary = dict),
        setValidator: (func, dict) => {
            validatorCallBack = func;
            cellDictionary = dict;
        },
        theTable: () => theTable,
        cancelEdit
    };
    // =======================================
    // Jumper sub-module
    // =======================================
    function jumper(table) {
        'use strict';
        let ri = 0, ci = 0;
        let theTable = (typeof table === 'string') ? document.getElementById(table) : table;
        let nr = 0, nc = 0;
        let escape = false;
        let fake = 0;
        let lastDir = 1;
        refresh();
        return {refresh, clickJump, updown, clickHere, escapeEdit, fakeClick};
        function refresh() {
            nr = theTable.rows.length;
            nc = theTable.rows[nr - 1].cells.length;
        }

        function updown(e) {
            escape = false;
            const code = e.keyCode;
            const td = e.target.parentNode;
            ri = td.parentNode.rowIndex;
            ci = td.cellIndex;
            nr = theTable.rows.length;
            nc = theTable.rows[ri].cells.length;
            switch (code) {
                case 13:
                case 9:
                {
                    e.stopPropagation();
                    e.preventDefault();
                    const dir = (code === 9 && e.shiftKey) ? -1 : 1;
                    clickJump(dir);
                    break;
                }
                case 27:
                    escape = true;
                    fake = 0;
                    clickHere(ri, ci);
                    break;
                case 38:
                    nextRow(-1);
                    break;
                case 40:
                    nextRow(1);
                    break;
            }
        }

        function clickJump(dir) {
            if (dir === 0) {
                dir = lastDir;
            }
            lastDir = dir;
            nextCell(dir);
            clickHere(ri, ci);
        }

        function clickHere(row, col) {
            fake = 1;
            const targetCell = theTable.rows[row].cells[col];
            const evt = new MouseEvent('click', {bubbles: true, cancelable: true});
            targetCell.dispatchEvent(evt);
        }

        function escapeEdit() {
            const wasEscape = escape;
            escape = false;
            return wasEscape;
        }

        function fakeClick() {
            const f = fake;
            fake = 0;
            return f;
        }

        function nextRow(dir) {
            do {
                ri = (ri + dir + nr) % nr;
            } while (theTable.rows[ri].cells[0].tagName !== 'TD');
            clickHere(ri, ci);
        }

        function nextCell(dir) {
            do {
                ci += dir;
                if (ci >= theTable.rows[ri].cells.length) {
                    ci = 0;
                    ri = (ri + 1) % nr;
                } else if (ci < 0) {
                    ri = (ri - 1 + nr) % nr;
                    ci = theTable.rows[ri].cells.length - 1;
                }
            } while (theTable.rows[ri].cells[ci].tagName !== 'TD');
        }
    }
}
