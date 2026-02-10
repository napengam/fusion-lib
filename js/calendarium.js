const gebi = (id) => document.getElementById(id);

/**
 * hgsCalendar (fusion-lib)
 * -----------------------
 * Backend-driven popup calendar.
 *
 * JS CONTRACT:
 * - Interact ONLY with `.fusion-*` classes and `data-*` attributes
 * - Never depend on tag names or framework classes
 * - Styling is handled exclusively by CSS
 */

function hgsCalendar() {
    'use strict';

    let calendar = null;
    let backEnd = '';
    let callPHP = null;
    let gtarget = null;
    let tip = null;
    let outsideClickBound = false;

    /* ---------------------------------
     * Lifecycle hooks (all optional)
     * --------------------------------- */
    const hooks = {
        beforeShow: null,
        afterShow: null,
        beforeSelect: null,
        afterSelect: null,
        onClose: null
    };

    /* ---------------------------------
     * Public API
     * --------------------------------- */
    function fetchCalendar(m, y, target) {
        if (!backEnd) {
            console.warn('hgsCalendar: backend path not set');
            return;
        }

        gtarget = target;

        if (!m || !y) {
            const v = gebi(target)?.value;
            if (v) {
                const p = v.split('.');
                if (p.length >= 3) {
                    [, m, y] = p;
                }
            }
        }

        callPHP ??= new myBackend();
        callPHP.callDirect(backEnd, {y, m, target}, renderCalendar);
    }

    function closeCalendar() {
        if (calendar) {
            calendar.style.display = 'none';
        }

        tip?.closeTip?.();
        hooks.onClose?.(gtarget);
    }

    function setBackEnd(path) {
        backEnd = path;
    }

    function on(eventName, handler) {
        if (eventName in hooks && typeof handler === 'function') {
            hooks[eventName] = handler;
        }
    }

    /* ---------------------------------
     * Rendering & positioning
     * --------------------------------- */
    function renderCalendar(recPkg) {
        hooks.beforeShow?.(gtarget);

        if (!calendar) {
            calendar = document.createElement('div');
            document.body.appendChild(calendar);
            calendar.addEventListener('click', handleCalendarClick);
        }

        calendar.innerHTML = recPkg.result;
        calendar.style.position = 'absolute';
        calendar.style.display = 'inline-block';
        calendar.style.zIndex = highestZIndex();

        const input = gebi(gtarget);
        if (!input) {
            return;
        }

        const r = input.getBoundingClientRect();
        calendar.style.left = `${r.left + window.scrollX}px`;
        calendar.style.top = `${r.bottom + window.scrollY}px`;

        if (typeof toolTip === 'function') {
            tip = new toolTip();
        }

        if (!outsideClickBound) {
            document.addEventListener('click', handleOutsideClick, true);
            outsideClickBound = true;
        }

        hooks.afterShow?.(gtarget, calendar);
    }

    /* ---------------------------------
     * Event handling (fusion-aligned)
     * --------------------------------- */
    function handleCalendarClick(e) {
        const nav = e.target.closest(
                '.fusion-calendar-prev, .fusion-calendar-next'
                );
        if (nav) {
            const [m, y, target] = nav.dataset.myv.split('|');
            fetchCalendar(m, y, target);
            e.preventDefault();
            return;
        }

        const cell = e.target.closest('.fusion-calendar-day');
        if (!cell) {
            return;
        }

        const theDate = cell.dataset.thedate;
        if (!theDate) {
            return;
        }

        const input = gebi(gtarget);
        if (!input) {
            return;
        }

        if (hooks.beforeSelect?.(theDate, input) === false) {
            return;
        }

        input.value = theDate;
        closeCalendar();
        input.onchange?.();
        hooks.afterSelect?.(theDate, input);
    }

    function handleOutsideClick(e) {
        if (!calendar || calendar.style.display === 'none') {
            return;
        }

        const input = gebi(gtarget);
        if (calendar.contains(e.target) || e.target === input) {
            return;
        }

        closeCalendar();
    }

    /* ---------------------------------
     * Utilities
     * --------------------------------- */
    function highestZIndex() {
        let maxZ = 51;
        document.querySelectorAll('body *').forEach((el) => {
            const z = parseInt(getComputedStyle(el).zIndex, 10);
            if (!Number.isNaN(z)) {
                maxZ = Math.max(maxZ, z);
            }
        });
        return maxZ + 1;
    }

    /* ---------------------------------
     * Public surface
     * --------------------------------- */
    return {
        fetchCalendar,
        closeCalendar,
        backEnd: setBackEnd,
        on
    };
}

/* ---------------------------------
 * Global singleton
 * --------------------------------- */
window.HGS_CALENDAR = hgsCalendar();
