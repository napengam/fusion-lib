function tableEdit(idx) {
    'use strict';
    // =======================================
    // Internal state
    // =======================================
    let ri = 0,
            ci = 0,
            lastMove = 'Tab',
            theTable = '',
            id = '',
            cellDictionary = null,
            validatorCallBack = null,
            updateCallBack = null,
            insertRowCallBack = null, // called from context menue
            deleteRowCallBack = null, // called from context menue
            copyRowCallBack = null;// called from context menue
    let errorCallBack = () => {
    };
    let confirmCallBack = () => {
    };
    let editing = {
        td: null,
        editor: null,
        next: null
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
    theTable.tBodies[0].addEventListener('click', cellEdit);
    // =======================================
    // Cell click
    // =======================================
    function cellEdit(e) {
        const td = getTD(e.target);
        if (!td) {
            return;
        }
        if (editing.td === td) {
            return;
        }
        lastMove = 'click'
        let options = '';
        let leftOverEditor = theTable.querySelector('.theField');
        if (leftOverEditor) {
            // *****************************************
            // cleanup 
            // ******************************************
            if (editing.editor.changed()) {
                editing.editor.node.dispatchEvent(
                        new Event("change", {bubbles: true, cancelable: true})
                        );
            } else {

                editing.editor.node.dispatchEvent(
                        new Event("nochange", {bubbles: true, cancelable: true})
                        );
            }
            return;
        }

        if (cellDictionary) {
            options = cellDictionary[td.cellIndex];
        }
//        if (options?.skip === 'yes' && e.isTrusted) {
//            navigateFrom(td, lastMove === 'ShiftTab' ? 'ShiftTab' : 'Tab');
//            return;
//        }
        openEditor(td, options);
    }
    // =======================================
    // Open Editor
    // =======================================
    function openEditor(td, options) {

        if (options?.skip === 'yes' && lastMove !== 'click') {
            navigateFrom(td, lastMove === 'ShiftTab' ? 'ShiftTab' : 'Tab');
            return;
        }
        lastMove === 'click' ? lastMove = 'Tab' : '';
        let editor = createEditor(td, options);

        editing.td = td;
        editing.editor = editor;
        editing.next = 'Tab';

        editor.node.addEventListener('keydown', isexit);
        editor.node.addEventListener('click', (e) => {
            e.stopPropagation();// do not enter cellEdit agaian
        });
        editor.node.focus();
    }
    // =======================================
    // Exit Handling
    // =======================================
    function isexit(e) {
        switch (e.key) {
            case "ArrowUp":
            case "ArrowDown":
            case "Tab":
            case "Enter":
            {
                e.preventDefault();
                let next = e.key;
                if (e.shiftKey && e.key === 'Tab') {
                    next = "ShiftTab";
                }
                lastMove = next;
                editing.next = next;
                exitEditor();
                break;
            }
            case "Escape":
            {
                e.preventDefault();
                editing.editor.cancel(editing.next);
                break;
            }
        }
    }
    function exitEditor() {
        if (!editing.editor) {
            return;
        }
        if (editing.editor.changed()) {
            editing.editor.node.dispatchEvent(
                    new Event("change", {bubbles: true, cancelable: true})
                    );
        } else {
            editing.editor.node.dispatchEvent(
                    new Event("nochange", {bubbles: true})
                    );
        }
    }
    // =======================================
    // Editor Factory
    // =======================================
    const editorRegistry = {};
    function registerEditor(type, factory) {
        editorRegistry[type] = factory;
    }
    function createEditor(td, options) {
        const type = options?.type || 'text';


        switch (options.type) {
            case 'text':
            {
                return createTextEditor(td, options);
                break;
            }
            case 'select':
            {
                return createSelectEditor(td, options);
                break;
            }
            default:
                return createTextEditor(td, options);
                break;
        }
    }

    function createTextEditor(td, options) {

        function getValueFromParent() {
            return td.textContent;
        }

        let originalValue = getValueFromParent();
        td.dataset.oldvalue = td.innerHTML;
        let confirmClass = options?.confirm === 'yes' ? 'need-confirm' : '';
        td.innerHTML = `<input id='theField' class="theField ${confirmClass}"
            type="text"
            value="${originalValue}"
            data-master='change'
            data-oldvalue="${originalValue}"
            maxlength="${options?.len || 50}"
            size="${options?.len || 20}">`;
        let node = td.querySelector('.theField');

        node.onclick = function (e) {
            if (options.type === 'date') {
                HGS_CALENDAR.fetchCalendar(null, null, 'theField');
                node.onchange = commit; // click in calendar will call this
            }
        };
        node.removeEventListener("change", commit);
        node.removeEventListener("nochange", cancel);

        node.addEventListener("change", commit);
        node.addEventListener("nochange", cancel);


        function commit() {
            if (cellDictionary && validatorCallBack) {
                const vcb = validatorCallBack(node.value, cellDictionary[td.cellIndex]);
                if (!vcb.ok) {
                    errorCallBack(`${node.value} ?? ${vcb.msg}`);
                    node.removeEventListener("change", commit);
                    newFirstChild(td, originalValue);
                    openEditor(td, options);
                    return;
                }
            }
            node.removeEventListener("change", commit);
            newFirstChild(td, node.value);
            navigateFrom(td, lastMove);

        }
        function cancel() {
            node.removeEventListener("change", commit);
            newFirstChild(td, originalValue);
            if (lastMove) {
                navigateFrom(td, lastMove);
            }
        }
        return {
            node,
            changed() {
                return node.value !== originalValue;
            }
        };
    }
    function createSelectEditor(td, options) {
        function getValueFromParent() {
            return td.textContent;
        }

        let originalValue = getValueFromParent();
        td.dataset.oldvalue = td.innerHTML;
        const optionsHtml = options.options.map(o => {
            let value, text;
            if (typeof o === 'string' && o.includes('|')) {
                [value, text] = o.split('|');
            } else {
                value = o;
                text = o;
            }
            const selected = (value === originalValue) ? 'selected' : '';
            return `<option value="${htmlentity(value)}" ${selected}>${htmlentity(text)}</option>`;
        }).join('');
        td.innerHTML = `<select class="theField " data-oldvalue="${originalValue}">${optionsHtml}</select>`;
        let node = td.querySelector('.theField');
        node.removeEventListener("change", commit);
        node.removeEventListener("nochange", cancel);
        node.addEventListener("change", commit);
        node.addEventListener("nochange", cancel);

        function commit() {

            newFirstChild(td, node.value);
            navigateFrom(td, lastMove);
        }
        function cancel() {
            newFirstChild(td, originalValue);
            if (lastMove) {
                navigateFrom(td, lastMove);
            }
        }
        return {
            node,
            changed() {
                return node.value !== originalValue;
            }
        };
    }

    // *****************************************
    // seting td content 
    // ******************************************


    function newFirstChild(container, newContent,
    {
    allowHTML = true,
            protectedSelectors = [
                    ".comment-icon",
                    ".comment-text",
                    ".atticon",
                    ".attlist"
            ],
            insertBeforeProtected = true
    } = {}
    ) {
        /**
         * Inserts content as first non-protected child.
         * Preserves nodes matching protectedSelectors.
         */
        if (!(container instanceof HTMLElement)) {
            return;
        }
        const protectedSet = new Set();
        if (protectedSelectors && protectedSelectors.length) {
            container.querySelectorAll(protectedSelectors.join(","))
                    .forEach(el => protectedSet.add(el));
        }
        // ---------------------------------------
        // Remove all non-protected children
        // ---------------------------------------
        for (let i = container.childNodes.length - 1; i >= 0; i--) {
            const node = container.childNodes[i];
            if (!protectedSet.has(node) && node.parentNode === container) {
                try {
                    node.remove();
                } catch {

                }
            }
        }
        // ---------------------------------------
        // Build node to insert
        // ---------------------------------------
        let nodeToInsert = null;

        if (newContent instanceof Node) {
            nodeToInsert = newContent;
        } else if (typeof newContent === "string") {
            if (allowHTML) {
                const range = document.createRange();
                nodeToInsert = range.createContextualFragment(newContent);
            } else {
                nodeToInsert = document.createTextNode(newContent);
            }
        }
        if (!nodeToInsert) {
            return;
        }
        // ---------------------------------------
        // Insert node
        // ---------------------------------------
        if (insertBeforeProtected && protectedSet.size > 0) {
            const firstProtected = Array.from(container.childNodes)
                    .find(n => protectedSet.has(n));
            if (firstProtected) {
                container.insertBefore(nodeToInsert, firstProtected);
                return;
            }
        }
        container.appendChild(nodeToInsert);
    }


    // =======================================
    // Navigation
    // =======================================
    function navigateFrom(td, next = 'Tab') {

        let ri = td.parentNode.rowIndex;
        let ci = td.cellIndex;
        let nc = td.parentElement.cells.length;
        let nr = theTable.rows.length;
        let nh = theTable.tHead ? theTable.tHead.rows.length : 0;
        const moves = {
            ArrowUp: {r: -1, c: 0},
            ArrowDown: {r: 1, c: 0},
            Tab: {r: 0, c: 1},
            Enter: {r: 0, c: 1},
            ShiftTab: {r: 0, c: -1}
        };
        if (!moves[next]) {
            return;
        }
        ri += moves[next].r;
        ci += moves[next].c;
        if (ci < 0) {
            ci = nc - 1;
            ri--;
        }
        if (ci >= nc) {
            ci = 0;
            ri++;
        }
        if (ri >= nr) {
            ri = nh;
        }
        if (ri < nh) {
            ri = nr - 1;
        }
        if (theTable.rows[ri] && theTable.rows[ri].cells[ci]) {
            let nextCell = theTable.rows[ri].cells[ci];
            let options = '';
            if (cellDictionary) {
                options = cellDictionary[nextCell.cellIndex];
            }
            openEditor(nextCell, options);
    }
    }

    // =======================================
    // Context Menu setup
    // =======================================
    let cm = new contextMenuF('', id);
    cm.addMenu([
        {id: 'insert', icon: '<i class="fa fa-sign-in-alt fa-flip-horizontal"></i>', label: ' insert row', handler: contextAddRow},
        {id: 'copy', icon: '<i class="fa fa-reply fa-flip-vertical"></i>', label: ' copy row', handler: contextCopyRow},
        {id: 'delete', icon: '<i class="fa fa-trash-alt"></i>', label: ' delete row...', handler: contextDeleteRow}
    ]);

    // =======================================
    // Delegated Event Binding
    // =======================================
    theTable.tBodies[0].addEventListener('contextmenu', handleContextMenu);
    // =======================================
    // Core Handlers
    // =======================================
    function handleContextMenu(e) {
        if (!e.target.closest('td'))
            return;

        if (theTable.dataset.ininput) {
            [ri, ci] = rici();
            const cell = theTable.rows[ri].cells[ci];
            cell.innerHTML = cell.dataset.oldvalue;
            theTable.dataset.ininput = '';
            cell.removeAttribute('data-editcursor');
        }
        cm.open(e);
    }

    // =======================================
    // Context Menu Handlers
    // =======================================
    function contextAddRow(e) {
        const here = walkUp(e.target);
        const newRow = here.table.insertRow(here.row.rowIndex);
        [...here.row.cells].forEach(() => newRow.insertCell());
        newRow.cells[0].innerHTML = '&nbsp;';
        const height = newRow.cells[0].clientHeight;
        newRow.cells[0].innerHTML = '';
        newRow.cells[0].style.height = height + 'px';
        return newRow.rowIndex;
    }

    function contextDeleteRow(e) {
        confirmCallBack('Delete row?', () => {
            const here = walkUp(e.target);
            here.table.deleteRow(here.row.rowIndex);
        }, () => {
        });
    }

    function contextCopyRow(e) {
        const here = walkUp(e.target);
        const newRow = here.table.insertRow(here.row.rowIndex);
        [...here.row.cells].forEach((cell, i) => {
            newRow.insertCell();
            newRow.cells[i].innerHTML = cell.innerHTML;
        });
        newRow.cells[0].style.height = '18px';

        return newRow.rowIndex;
    }


    // =======================================
    // Utilities
    // =======================================
    function walkUp(src) {
        const contextMenu = src.closest('.contextParent');
        const tabId = contextMenu.dataset.owner;
        const obj = contextMenu.target;
        return {col: obj, row: obj.parentNode, table: obj.closest(`#${tabId}`)};
    }
    function getTD(obj) {
        let td = obj.closest('td');
        if (!td) {
            return null;
        }
        let table = td.closest('table');
        if (!table || table.id !== id) {
            return null;
        }
        ri = td.parentNode.rowIndex;
        ci = td.cellIndex;
        return td;
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
    // =======================================
    // Public API
    // =======================================
    return {
        registerEditor: registerEditor,
        setUpdateCallBack: (func) => (updateCallBack = func),
        setErrorCallBack: (func) => (errorCallBack = func),
        setConfirmCallBack: (func) => (confirmCallBack = func),
        setInsertRowCallBack: (func) => (insertRowCallBack = func),
        setDeleteRowCallBack: (func) => (deleteRowCallBack = func),
        setCopyRowCallBack: (func) => (copyRowCallBack = func),
        setDictionary: (dict) => (cellDictionary = dict),
        setValidator: (func, dict) => {
            validatorCallBack = func;
            cellDictionary = dict;
        },
        theTable: () => theTable
    };
}
