const gebi = (id) => document.getElementById(id);

/**
 * hgsCalendar
 * ------------
 * Backend-driven popup calendar with lifecycle hooks.
 * Hooks allow integration with frameworks and UI events.
 */
function hgsCalendar() {
    'use strict';

    let calendar = null;
    let backEnd = '';
    let callPHP = null;
    let gtarget = null;
    let tip = null;
    let outsideClickBound = false;
    let autoAttachEnabled = false;

    // Hook placeholders (all optional)
    const hooks = {
        beforeShow: null,
        afterShow: null,
        beforeSelect: null,
        afterSelect: null,
        onClose: null,
    };

    function fetchCalendar(m, y, target) {
        if (backEnd === '') {
            console.warn('hgsCalendar: backend path not set.');
            return;
        }

        gtarget = target;

        if (!m || !y) {
            const v = gebi(target)?.value;
            if (v) {
                const parts = v.split('.');
                if (parts.length >= 3) {
                    [, m, y] = parts;
                }
            }
        }

        const jsonPost = { y, m, target };
        callPHP ??= new myBackend();
        callPHP.callDirect(backEnd, jsonPost, readResponse);
    }

    function closeCalendar() {
        if (calendar) {
            calendar.style.display = 'none';
        }

        if (tip && typeof tip.closeTip === 'function') {
            tip.closeTip();
        }

        if (typeof hooks.onClose === 'function') {
            hooks.onClose(gtarget);
        }
    }

    function readResponse(recPkg) {
        if (typeof hooks.beforeShow === 'function') {
            hooks.beforeShow(gtarget);
        }

        if (!calendar) {
            calendar = document.createElement('div');
            document.body.appendChild(calendar);
        }

        Object.assign(calendar.style, {
            position: 'absolute',
            display: 'inline-block',
            zIndex: highestZIndex(),
        });

        calendar.innerHTML = recPkg.result;

        const inputEl = gebi(gtarget);
        const rect = inputEl.getBoundingClientRect();

        calendar.style.left = `${rect.x + window.scrollX}px`;
        calendar.style.top = `${rect.y + rect.height + window.scrollY}px`;

        calendar.onclick = tableCallback;

        const navLinks = calendar.querySelectorAll('a[data-myv]');
        navLinks.forEach((link) => {
            link.onclick = (e) => {
                e.preventDefault();
                const [newM, newY, newTarget] = link.getAttribute('data-myv').split('|');
                fetchCalendar(newM, newY, newTarget);
            };
        });

        if (typeof toolTip === 'function') {
            tip = new toolTip();
        }

        if (!outsideClickBound) {
            document.addEventListener('click', handleOutsideClick, true);
            outsideClickBound = true;
        }

        if (typeof hooks.afterShow === 'function') {
            hooks.afterShow(gtarget, calendar);
        }
    }

    function handleOutsideClick(e) {
        if (!calendar || calendar.style.display === 'none') {
            return;
        }

        const inputEl = gebi(gtarget);
        if (calendar.contains(e.target) || e.target === inputEl) {
            return;
        }

        closeCalendar();
    }

    function tableCallback(e) {
        const el = e.target;
        if (el.id === 'close') {
            closeCalendar();
            return;
        }

        const theDate = el.getAttribute('data-thedate');
        if (!theDate) {
            return;
        }

        const targetEl = gebi(gtarget);
        if (!targetEl) {
            return;
        }

        if (typeof hooks.beforeSelect === 'function') {
            const shouldContinue = hooks.beforeSelect(theDate, targetEl);
            if (shouldContinue === false) {
                return;
            }
        }

        targetEl.value = theDate;
        closeCalendar();

        if (typeof targetEl.onchange === 'function') {
            targetEl.onchange();
        }

        if (typeof hooks.afterSelect === 'function') {
            hooks.afterSelect(theDate, targetEl);
        }
    }

    function highestZIndex() {
        let maxZ = 51;
        document.querySelectorAll('*').forEach((el) => {
            const z = parseInt(window.getComputedStyle(el).zIndex, 10);
            if (!Number.isNaN(z)) {
                maxZ = Math.max(maxZ, z);
            }
        });
        return maxZ;
    }

    function setBackEnd(path) {
        backEnd = path;
    }

    function enableAutoAttach(flag = true) {
        if (!flag || autoAttachEnabled) {
            return;
        }

        autoAttachEnabled = true;

        document.addEventListener('focusin', (e) => {
            const el = e.target;
            if (el.matches('input[data-calendar]')) {
                const targetId = el.id || `cal_target_${Math.random().toString(36).slice(2)}`;
                if (!el.id) {
                    el.id = targetId;
                }
                fetchCalendar(null, null, targetId);
            }
        });
    }

    /**
     * Public hook registration API
     */
    function on(eventName, handler) {
        if (hooks.hasOwnProperty(eventName) && typeof handler === 'function') {
            hooks[eventName] = handler;
        } else {
            console.warn(`Unknown or invalid hook: ${eventName}`);
        }
    }

    // Public API
    return {
        fetchCalendar,
        closeCalendar,
        backEnd: setBackEnd,
        autoAttach: enableAutoAttach,
        on, // Hook registration
    };
}

window.HGS_CALENDAR = hgsCalendar();
