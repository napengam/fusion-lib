function addConfirm(options = {}) {
    // Mapping common event types to correct constructors
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
        submit: Event
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
        closeFn: () => {}
    };

    const config = Object.assign({}, defaults, options);
    const { selector, events, confirmFn, closeFn } = config;

    // --- Internal state ---
    let currentTarget = null;
    let currentEventType = null;
    const bypassMap = new WeakMap();

    // --- Helper: safely get element value ---
    function getElementValue(el) {
        if ('value' in el) {
            return el.value;
        }
        if (el.textContent && el.textContent.trim()) {
            return el.textContent.trim();
        }
        return null;
    }

    // --- Delegated event handler ---
    function handler(e) {
        const el = e.target.closest(selector);
        if (!el) {
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

        e.preventDefault();
        e.stopPropagation();

        currentTarget = el;
        currentEventType = e.type;

        const ask = el.dataset.ask || 'Sicher?';
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
        const evt = new EventConstructor(evtType, {
            bubbles: true,
            cancelable: true
        });
        currentTarget.dispatchEvent(evt);

        // Emit custom yeschange event
        const val = getElementValue(currentTarget);
        currentTarget.dispatchEvent(
            new CustomEvent('yeschange', {
                bubbles: true,
                detail: { value: val, type: evtType }
            })
        );

        closeFn();
        currentTarget = null;
        currentEventType = null;
    }

    // --- Confirmation cancelled ---
    function onNo() {
        if (currentTarget) {
            const val = getElementValue(currentTarget);
            currentTarget.dispatchEvent(
                new CustomEvent('nochange', {
                    bubbles: true,
                    detail: { value: val, type: currentEventType }
                })
            );
        }
        closeFn();
        currentTarget = null;
        currentEventType = null;
    }

    // --- Register handlers globally ---
    for (const type of events) {
        document.body.addEventListener(type, handler, true);
    }

    // --- Store singleton instance ---
    addConfirm._instance = { handler };
    return addConfirm._instance;
}
