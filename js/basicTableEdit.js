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
            changeCallBack = null;
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
        lastMove = 'click';
        let options = '';
        let leftOverEditor = theTable.querySelector('.theField');
        if (leftOverEditor) {
            let oldtd = leftOverEditor.parentNode;
            leftOverEditor.remove();
            oldtd.innerHTML = oldtd.dataset.oldvalue;
        }

        if (cellDictionary) {
            options = cellDictionary[td.cellIndex];
        }
        if (options?.skip === 'yes' && !e.isTrusted) {
            navigateFrom(td, lastMove === 'ShiftTab' ? 'ShiftTab' : 'Tab');
            return;
        }
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
        let editor = createEditor(td, options);

        editing.td = td;
        editing.editor = editor;
        editing.next = 'Tab';
        editor.node.addEventListener('keydown', isexit);
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
            editing.editor.commit(editing.next);
        } else {
            editing.editor.cancel(editing.next);
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
        const factory = editorRegistry[type] || editorRegistry['text'];
        return factory(td, options);
    }
    registerEditor('text', createTextEditor);
    registerEditor('select', createSelectEditor);

    function createTextEditor(td, options) {
        function getValueFromParent() {
            return td.textContent;
        }
        function setValueToParent(value) {
            td.innerHTML = value;
        }
        let originalValue = getValueFromParent();
        td.dataset.oldvalue = td.innerHTML;
        let confirmClass = options?.confirm === 'yes' ? 'need-confirm' : '';
        td.innerHTML = `<input class="theField ${confirmClass}"
            type="text"
            value="${originalValue}"
            data-master='change'
            data-oldvalue="${originalValue}"
            maxlength="${options?.len || 50}"
            size="${options?.len || 20}">`;
        let node = td.querySelector('.theField');
        return {
            node,
            changed() {
                return node.value !== originalValue;
            },
            commit(next) {
                if (cellDictionary && validatorCallBack) {
                    const vcb = validatorCallBack(node.value, cellDictionary[td.cellIndex]);
                    if (!vcb.ok) {
                        errorCallBack(`${node.value} ?? ${vcb.msg}`);
                        return;
                    }
                }
                setValueToParent(node.value);
                navigateFrom(td, next);
            },
            cancel(next) {
                setValueToParent(originalValue);
                if (next) {
                    navigateFrom(td, next);
                }
            }
        };
    }
    function createSelectEditor(td, options) {
        function getValueFromParent() {
            return td.textContent;
        }
        function setValueToParent(value) {
            td.innerHTML = value;
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
        td.innerHTML = `<select class="theField" data-oldvalue="${originalValue}">${optionsHtml}</select>`;
        let node = td.querySelector('.theField');
        return {
            node,
            changed() {
                return node.value !== originalValue;
            },
            commit(next) {
                setValueToParent(node.value);
                navigateFrom(td, next);
            },
            cancel(next) {
                setValueToParent(originalValue);
                if (next) {
                    navigateFrom(td, next);
                }
            }
        };
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
    // Utilities
    // =======================================
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
        setChangeCallBack: (func) => (changeCallBack = func),
        setErrorCallBack: (func) => (errorCallBack = func),
        setConfirmCallBack: (func) => (confirmCallBack = func),
        setDictionary: (dict) => (cellDictionary = dict),
        setValidator: (func, dict) => {
            validatorCallBack = func;
            cellDictionary = dict;
        },
        theTable: () => theTable
    };
}
