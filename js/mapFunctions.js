function mapFunctions(id, selectorClass = '[data-funame]') {
    const obj = (typeof id === 'string') ? document.getElementById(id) : id;
    if (!obj) return;

    if (obj.matches?.(selectorClass)) {
        mapFunc(obj);
    }

    obj.querySelectorAll(selectorClass).forEach(mapFunc);

    function mapFunc(element) {
        const eventList = element.dataset.event
            ? element.dataset.event.split(',').map(e => e.trim() || 'click')
            : ['click'];

        const funameList = element.dataset.funame
            ? element.dataset.funame.split(',').map(f => f.trim())
            : [];

        eventList.forEach((ev, idx) => {
            const funame = funameList[idx];
            if (!funame) {
                console.warn('mapFunctions: missing funame for event', ev, element);
                return;
            }

            const handler = getFunctionByPath(funame);
            if (typeof handler === 'function') {
                element.removeEventListener(ev, handler, false);
                element.addEventListener(ev, handler, false);
            } else {
                console.warn('mapFunctions: handler not found for', funame);
            }
        });
    }

    function getFunctionByPath(path, root = window) {
        if (!path) return null;
        const parts = path.split(".");
        let obj = root;
        for (const part of parts) {
            if (obj && part in obj) {
                obj = obj[part];
            } else {
                return null;
            }
        }
        return typeof obj === "function" ? obj : null;
    }
}