/*
 * function looks in container with id for elements with a giben  selector.
 * Iterates over these elements and looks for data-event , data-funame pairs
 * If datat-event is not set onclick is default.
 * 
 * <div id=id>
 * <button    data-funame='objectOfFunctions.save'> Save  </button>
 * <button    data-funame='objectOfFunctions.cancle'> Cancle </button>
 * <button    data-funame='objectOfFunctions.copy'> Copy  </button>
 * </div>
 * 
 mapFunctions('id', '.selectorClass');
 
 */

function mapFunctions(id, selectorClass = '[data-funame]') {

    // Get the DOM element based on the id type
    const obj = (typeof id === 'string') ? document.getElementById(id) : id;
    // Return early if the object is not valid
    if (!obj) {
        return;
    }

    // If obj has the selector class, map it directly
    if (obj.matches(selectorClass)) {
        mapFunc(obj);
    }

    // Select all elements within obj that match the selector class
    const elements = obj.querySelectorAll(selectorClass);
    elements.forEach(mapFunc); // Iterate over the list of elements

    // Function to map the event handlers to each element
    function mapFunc(element) {
        const eventList = (element.dataset.event ? element.dataset.event.split(',') : [null])
                .map(evt => evt || 'click'); // Ensure defaults are 'click'
        const funameList = element.dataset.funame ? element.dataset.funame.split(',') : [];

        // Loop through event list and bind appropriate functions
        eventList.forEach((ev, idx) => {
            const funame = funameList[idx];
            const handler = getFunctionByPath(funame);

            if (typeof handler === 'function') {
                element.removeEventListener(ev, handler, false);
                element.addEventListener(ev, handler, false);
            }
        });
    }
    function getFunctionByPath(path) {
        const parts = path.split(".");
        let obj = window;

        for (const part of parts) {
            if (obj && part in obj) {
                obj = obj[part];
            } else {
                return null; // function not found
            }
        }

        return typeof obj === "function" ? obj : null;
}

}

