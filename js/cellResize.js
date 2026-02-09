function initResize(tid) {
    // *****************************************
    // based on work done by https://github.com/phuocng in
    // https://github.com/phuocng/html-dom/blob/master/contents/resize-columns-of-a-table.md
    // ******************************************

    var rTable, tm, info, down, move, up, touch;
    if (typeof tid === 'undefined' || tid === null) {
        return;
    }
    if (typeof tid === 'string') {
        rTable = document.getElementById(tid);
    } else {
        rTable = tid;
    }
    if (rTable.dataset.hasresize) {
        return {
            setHookAfterResize: setHookAfterResize
        };
    }

    if (isTouchDevice() === false) {
        down = 'mousedown';
        move = 'mousemove';
        up = 'mouseup';
        touch = false;
    } else {
        down = 'touchstart';
        move = 'touchmove';
        up = 'touchend';
        touch = true;
    }

    rTable.dataset.hasresize = '1';
    tm = makeStyle();
    function createResizableTable() {
        info = getInfo();
        Array.from(info.cols).forEach(col => {
            // Using requestAnimationFrame to ensure DOM is ready

            const resizer = document.createElement('div');
            resizer.classList.add(`resizer_${tm}_`, 'findMe');

            // Dynamically set height based on table body
            const tbodyHeight = rTable.tBodies[0]?.offsetHeight || 0;
            resizer.style.height = `${tbodyHeight + info.height}px`;

            const stickyCols = parseInt(rTable.dataset.stickycols) || 0;
            if (col.cellIndex >= stickyCols) {
                col.style.position = 'relative';
            }
            col.appendChild(resizer);
            resizer.addEventListener(down, mouseDownHandler);
            resizer.draggable = false; // modern browsers do not need this and it avoids ghost drag images

        });
    }

    function mouseDownHandler(e) {
        if (!touch) {
            this.x = e.clientX;
        } else {
            e.stopPropagation();
            e.preventDefault();
            this.x = e.touches[0].clientX;
        }
        this.w = this.parentNode.getBoundingClientRect().width;
        rTable.div = this;
        document.removeEventListener(move, mouseMoveHandler);
        document.removeEventListener(up, mouseUpHandler);
        document.addEventListener(move, mouseMoveHandler);
        document.addEventListener(up, mouseUpHandler);
        this.classList.add(`resizing_${tm}_`);
    }

    function mouseMoveHandler(e) {
        const clientX = e instanceof TouchEvent ? e.touches[0].clientX : e.clientX;
        const dx = clientX - rTable.div.x;

        // Update column width dynamically
        rTable.div.parentNode.style.width = `${rTable.div.w + dx}px`;
    }


    function mouseUpHandler() {
        rTable.div.classList.remove(`resizing_${tm}_`);
        document.removeEventListener(move, mouseMoveHandler);
        document.removeEventListener(up, mouseUpHandler);
        hookAR();
    }
    function getInfo() {
        const info = {};

        if (rTable.tHead) {
            const lastRowIndex = rTable.tHead.rows.length - 1;
            const lastRow = rTable.tHead.rows[lastRowIndex];

            info.last = lastRowIndex;
            info.cols = Array.from(lastRow.cells);
            info.rows = Array.from(rTable.tHead.rows);
            info.height = lastRow.offsetHeight;
        } else {
            const firstRow = rTable.rows[0];
            info.last = 0;
            info.cols = Array.from(firstRow?.cells || []);
            info.rows = Array.from(rTable.rows);
            info.height = 0;
        }

        return info;
    }

    function makeStyle() {
        // Create unique timestamp for styles
        let tm;
        const existingStyle = Array.from(document.querySelectorAll('style')).find(elem => elem.innerText.includes('.resizer'));

        if (existingStyle) {
            tm = existingStyle.innerText.split('_')[1];
            return tm;
        }

        tm = Date.now();
        const styleElem = document.createElement('style');
        styleElem.innerHTML = `
        .resizer_${tm}_ {
            position: absolute;
            top: 0;
            right: 0;
            width: 2px;
            cursor: col-resize;
            user-select: none;
        }
        .resizer_${tm}_:hover,
        .resizing_${tm}_ {
            border-right: 2px solid blue;
        }
    `;
        document.head.appendChild(styleElem);
        return tm;
    }

    function hookAR() {
        // dummy
    }
    function setHookAfterResize(aFunc) {
        hookAR = aFunc;

    }
    function removeResizer() {
        rTable.querySelectorAll('.findMe').forEach((elem) => {
            elem.parentNode.removeChild(elem);
        });
        delete rTable.dataset.hasresize;
    }

    function isTouchDevice() { // from chatGPT
        return 'ontouchstart' in window;
    }
    createResizableTable();

    return {
        theTable: () => {
            return rTable;
        },
        setHookAfterResize: setHookAfterResize,
        removeResizer: removeResizer
    };
}
