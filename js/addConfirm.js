
/**
 * Adds confirmation logic to elements.
 * Shows a confirmation dialog only if the value has actually changed.
 * Supports: input, textarea, select, checkbox, radio, and contenteditable elements.
 * Ignores disabled or readonly elements.
 *
 * @param {Object} options - Configuration object.
 * @param {string} [options.selector='.need-confirm'] - CSS selector for target elements.
 * @param {string[]} [options.events=['click', 'change']] - List of event types to monitor.
 * @param {Function} [options.confirmFn=dialogs.myConfirm] - Function used to display the confirmation dialog.
 * @param {Function} [options.closeFn=dialogs.closeDiag] - Function used to close the dialog.
 * @returns {Object} - The stored singleton instance.
 */
function addConfirm(options = {}) {
    const EVENT_CONSTRUCTOR_MAP = {
        click: MouseEvent,
        mousedown: MouseEvent,
        mouseup: MouseEvent,
        keyup: KeyboardEvent,
        keydown: KeyboardEvent,
        keypress: KeyboardEvent,
        focus: FocusEvent,
        blur: FocusEvent,
        change: Event,
        input: Event,
        submit: Event,
    };
    // --- Singleton guard ---
    if (addConfirm._instance) {
        return addConfirm._instance;
    }

    // --- Default configuration ---
    const defaults = {
        selector: '.need-confirm',
        events: ['click', 'change'],
        confirmFn: (msg, yes, no) => {
            if (window.confirm(msg)) {
                yes();
            } else {
                no();
            }
        },
        closeFn: () => {
        }
    };

    const config = Object.assign({}, defaults, options);
    const {selector, events, confirmFn, closeFn} = config;

    // --- Internal state ---
    let currentTarget = null;
    let currentEventType = null;
    const bypassMap = new WeakMap();
    const initialValueMap = new WeakMap();

    // --- Helper: get element value/state ---
    function getElementValue(el) {
        if (!el) {
            return null;
        }
        if (el.type === 'checkbox' || el.type === 'radio') {
            return el.checked;
        }
        if (el.tagName === 'SELECT') {
            if (el.multiple) {
                return Array.from(el.selectedOptions).map(o => o.value).join(',');
            } else {
                return el.value;
            }
        }
        if ('value' in el) {
            return el.value;
        }
        if (el.isContentEditable || (el.textContent && el.textContent.trim())) {
            return el.textContent.trim();
        }
        return null;
    }

    // --- Helper: check if element can be interacted with ---
    function isInteractable(el) {
        if (!el) {
            return false;
        }
        if (el.disabled) {
            return false;
        }
        if (el.readOnly) {
            return false;
        }
        if (el.getAttribute('aria-disabled') === 'true') {
            return false;
        }
        return true;
    }

    // --- Capture initial value before interaction ---
    document.body.addEventListener('focusin', storeInitialValue, true);
    document.body.addEventListener('mousedown', storeInitialValue, true);

    function storeInitialValue(e) {
        const el = e.target.closest(selector);
        if (el && isInteractable(el)) {
            initialValueMap.set(el, getElementValue(el));
        }
    }

    // --- Delegated confirm handler ---
    function handler(e) {
        const el = e.target.closest(selector);
        if (!el || !isInteractable(el)) {
            return;
        }
        const master = el.dataset.master || 'click';
        if (master !== e.type) {
            return;
        }
        if (bypassMap.has(el)) {
            bypassMap.delete(el);
            return;
        }
        if (master === 'change') {
            const prevValue = initialValueMap.get(el);
            const currentValue = getElementValue(el);
            // Skip confirmation if value didn't change
            if (String(prevValue) === String(currentValue)) {
                return;
            }
        }
        e.preventDefault();
        e.stopPropagation();
        currentTarget = el;
        currentEventType = e.type;
        const ask = el.dataset.ask || 'Are you sure?';
        confirmFn(ask, onYes, onNo);
    }

    // --- Confirmation accepted ---
    function onYes() {
        if (!currentTarget) {
            closeFn();
            return;
        }
        bypassMap.set(currentTarget, true);
        const evtType = currentEventType || 'click';
        const EventConstructor = EVENT_CONSTRUCTOR_MAP[evtType] || Event;
        // Redispatch original event
        const evt = new EventConstructor(evtType, {bubbles: true, cancelable: true});
        currentTarget.dispatchEvent(evt);

        const val = getElementValue(currentTarget);
        currentTarget.dispatchEvent(
                new CustomEvent('yeschange', {bubbles: true, detail: {value: val, type: evtType}})
                );
        cleanup();

    }

    // --- Confirmation cancelled ---

    function onNo() {
        if (currentTarget) {
            const prevValue = initialValueMap.get(currentTarget);
            if (prevValue !== undefined) {
                if (currentTarget.tagName === "SELECT") {
                    if (currentTarget.multiple) {
                        Array.from(currentTarget.options).forEach(function (opt) {
                            opt.selected = prevValue.split(",").includes(opt.value);
                        });
                    } else {
                        currentTarget.value = prevValue;
                    }
                }
            }
            currentTarget.dispatchEvent(
                    new CustomEvent("nochange", {
                        bubbles: true,
                        detail: {
                            value: prevValue,
                            type: currentEventType
                        }
                    })
                    );
        }
        cleanup();
    }

    // --- Cleanup helper ---
    function cleanup() {
        // closeFn();
        currentTarget = null;
        currentEventType = null;
    }

    // --- Register handlers globally ---
    for (const type of events) {
        document.body.addEventListener(type, handler, true);
    }

    addConfirm._instance = {handler};
    return addConfirm._instance;
}
