//export {createEditor};

function createEditor(dialogs) {
    'use strict';
    let range = null, ui = document.querySelector('.miniRTE');
    if (ui) {
        return ui._api;
    }
    ui = document.createElement('div');
    ui.className = 'miniRTE';
    Object.assign(ui.style, {border: '1px solid gray', background: '#fff', display: 'none', padding: '4px'});
    ui.innerHTML =
            `<div style="border-bottom:1px solid #ccc;margin-bottom:4px">
<button data-cmd="bold"><b>B</b></button>
<button data-cmd="italic"><i>I</i></button>
<button data-cmd="underline"><u>U</u></button>
<button data-cmd="insertUnorderedList">•</button>
<button data-cmd="insertOrderedList">1.</button>
<button data-cmd="createLink">🔗</button>
<select data-cmd="fontName">
<option value="">Font</option>
<option>Arial</option><option>Courier</option><option>Times</option>
</select>
<select data-cmd="fontSize">
<option value="">Size</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option>
</select>
<select data-cmd="foreColor">
<option value="">Color</option><option value="black">black</option><option value="red">red</option><option value="green">green</option><option value="blue">blue</option>
</select>
<button data-act="save">✓</button>
<button data-act="close">✕</button>
</div>
<div class="ed" contenteditable style="min-height:120px"></div>`;
    document.body.appendChild(ui);
    const ed = ui.querySelector('.ed');
    let host = null;
    let orig = '';
    let saveCb = null;
    let closeCb = null;
    function exec(c, v) {
        ed.focus();
        restore();
        document.execCommand(c, false, v || null);
    }
    function attach(el) {
        el = this;
        close();
        host = el;
        orig = el.innerHTML;
        ed.innerHTML = orig;
        el.innerHTML = '';
        el.appendChild(ui);
        ui.style.display = 'block';
        ed.focus();
    }
    function save() {
        if (!host) {
            return;
        }
        host.innerHTML = ed.innerHTML;
        ui.style.display = 'none';
        document.body.appendChild(ui);
        host = null;
        if (saveCb) {
            saveCb();
        }
    }
    function close() {
        if (!host) {
            return;
        }
        host.innerHTML = orig;
        ui.style.display = 'none';
        document.body.appendChild(ui);
        host = null;
        if (closeCb) {
            closeCb();
        }
    }
    function saveSel() {
        range = null;
        const s = window.getSelection();
        if (s.rangeCount) {
            range = s.getRangeAt(0);
        }
    }
    function restore() {
        if (!range) {
            return;
        }
        const s = window.getSelection();
        s.removeAllRanges();
        s.addRange(range);
        range = null;
    }
    ui.addEventListener('click', (e) => {
        const b = e.target.closest('[data-cmd],[data-act]');
        if (!b) {
            return;
        }
        if (b.dataset.cmd === 'createLink') {
            saveSel();
            const url = dialogs.myPrompt("Enter URL:", "https://", (url) => {
                if (url) {

                    exec('createLink', url);
                }
            });

        } else if (b.dataset.cmd) {
            exec(b.dataset.cmd);
        }
        if (b.dataset.act === 'save') {
            save();
        }
        if (b.dataset.act === 'close') {
            close();
        }
    });
    ui.addEventListener('change', (e) => {
        const c = e.target.dataset.cmd;
        if (!c) {
            return;
        }
        exec(c, e.target.value);
    });
    const api = {
        attachEditor: attach,
        closeEditor: close,
        saveCallback: function (f) {
            saveCb = typeof f === 'function' ? f : null;
        },
        closeCallback: function (f) {
            closeCb = typeof f === 'function' ? f : null;
        }
    };
    ui._api = api;
    return api;
}