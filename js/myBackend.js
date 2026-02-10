/* global eval, key, csrf */

function myBackend(v)
{
    'use strict';
    var
            request, sendPkg, backEnd, respondAction,
            queue = [], timeOut = 0, router = '',
            setVeil = true, noQueue = false, dialogs = null,
            veil, reveal = {};
    //
    // these functions will be returned to the caller of this module
    //
    reveal = {
        sendNow: sendNow,
        useVeil: useVeil, // (true||false);
        callDirect: callDirect, //(backEndScript, sendPkg, respondAction)    
        setTimeout: setTimeout, //(milliSeconds) default=1000
        setNoQueue: setNoQueue, //(true||false)
        fetchHTML: fetchHTML, // (pfid, backend_script, payload, onDoneFunc)
        setRouter: setRouter,
        setDialogs: setDialogs
    };
    if (typeof v !== 'undefined') {
        setVeil = false;
    }
    function dummy() {
        return true;
    }
    request = new XMLHttpRequest();
    if (!request || (request.readyState !== 4 && request.readyState !== 0)) {
        queue.length = 0;
        return;
    }
    veil = document.getElementById('fetchBackend');
    if (!veil) {
        veil = document.createElement('DIALOG');
        veil.id = 'fetchBackend';
        veil.style.visibility = 'hidden';
        document.body.appendChild(veil);
        let styleElem = document.createElement('STYLE');
        styleElem.innerHTML = [
            "#fetchBackend::backdrop{opacity:0;background:red;position:fixed;top:0px;right:0px;bottom:0px;left:0px;}"
        ].join('');
        document.getElementsByTagName('head')[0].appendChild(styleElem);
    }

    //
    // send very first request imediatly
    // then queue requests
    //

    function callDirect(backEnd, sendPkg, respondAction) {
        if (respondAction === '') {
            respondAction = dummy;
        }
        if (typeof sendPkg.key === 'undefined' && typeof key !== 'undefined') {
            sendPkg.key = key;//hgs 26.03.2021
        }
        if (typeof sendPkg.csrf === 'undefined' && typeof csrf !== 'undefined') {
            sendPkg.csrf = csrf;//hgs 08.09.2024
        }
        if (router && typeof sendPkg.NOROUTE === 'undefined') {
            sendPkg.file = backEnd;
            backEnd = router;
        }
        queue.push({
            'backEnd': backEnd,
            'sendPkg': JSON.stringify(sendPkg),
            'respondAction': respondAction
        });
        if (queue.length === 1 || noQueue) {
            callCore(); // very first request or no queueing
        }
    }
    //
    // send request imediatly
    //
    function callCore() {

        if (queue.length === 0) {
            return;
        }
        //************************************************
        // process first request in queue
        //************************************************
        sendPkg = queue[0].sendPkg;
        respondAction = queue[0].respondAction;
        backEnd = queue[0].backEnd;

        request.open("POST", backEnd, true);
        request.setRequestHeader("Content-Type", "application/json");
        request.onreadystatechange = onChange;
        request.timeout = timeOut;
        request.ontimeout = timedOut;
        //
        // activate veil. to avoid any user interaction until request 
        // is finished or timed out;
        //   
        if (setVeil) {
            veil.showModal();
        }
        request.send(sendPkg);
    }
    function onChange() {
        if (this.readyState !== 4) {
            return;
        }
        queue.shift();
        veil.close();
        if (this.status !== 200) {
            try {
                const js = JSON.parse(this.responseText);
                respondAction(js);
            } catch (e) {
                respondAction({
                    'error': `<div style="width:60%; word-wrap: break-word;">${this.responseText} ${e.message}</div>`
                });
            }
            callCore(); // Process remaining requests in queue
            return;
        }
        this.onreadystatechange = '';
        try {
            const js = JSON.parse(this.responseText);
            respondAction(js);
        } catch (e) {
            respondAction({
                'error': `<div style="width:60%; word-wrap: break-word;">${this.responseText} ${e.message}</div>`
            });
        }
        callCore(); // Process remaining requests in queue
    }

    function timedOut() {
        // request timed out, take away veil.;
        queue.shift();
        veil.close();
        request.abort();
        respondAction({'error': `Backend script ${backEnd} timed out after ${timeOut} milliseconds: no response`});
        callCore();// process any remaining requests in queue
    }

    function setTimeout(n) {
        timeOut = n;
    }
    function setNoQueue(flag) {
        noQueue = flag;
    }
    function useVeil(flag) {
        setVeil = flag;//  true || false
    }
    function fetchHTML(pfid, backend_script, payload, onDoneFunc) {
        'use strict';

        ////////////////
        // pfid is the id of an html element to fill with content
        // delivered by the backend_script
        // If this html element does yet not exist we create a div
        // to hold the content. 
        ////////////////
        payload.pfid = pfid;
        callDirect(backend_script, payload, getResponds);

        function getResponds(recPkg) {
            const dd = document.getElementById(recPkg.pfid);
            if (!dd) {
                return;
            }
            if (recPkg.error) {
                recPkg.result = recPkg.error;
                if (dialogs) {
                    dialogs.myAlert(recPkg.error);
                    return;
                }
            }

            // Update the inner HTML and display the element
            dd.innerHTML = recPkg.result;
            dd.style.display = 'block';

            // Load any CSS files linked within the response
            [...dd.getElementsByTagName('link')].forEach(link => {
                if (link.type === 'text/css' && link.rel === 'stylesheet') {
                    includeCSS(link.href);
                }
            });

            // Execute any inline JavaScript or load external JS files
            [...dd.getElementsByTagName('script')].forEach(script => {
                if (!script.src) {
                    (0, eval)(script.innerHTML); // Execute inline scripts in global scope
                } else {
                    includeJS(script.src); // Load external scripts
                }
            });

            // Call onDoneFunc if defined as a function
            if (typeof onDoneFunc === 'function') {
                onDoneFunc(recPkg);
                mapFunctions(dd);
            }
        }


        function includeJS(file) {
            if ([...document.getElementsByTagName('script')].some(script => script.src.includes(file))) {
                return;
            }
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = file;
            document.head.appendChild(script);
        }

        function includeCSS(path) {
            if ([...document.getElementsByTagName('link')].some(link => link.href.includes(path))) {
                return;
            }
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.type = 'text/css';
            link.href = path;
            document.head.appendChild(link);
        }
    }


    function setRouter(r) {
        router = r;
    }
    function setDialogs(d) {
        dialogs = d;
    }

    function sendNow(backEnd, sendPkg) {
        // NOTE: The 'respondAction' parameter is now ignored, as sendBeacon
        // is fire-and-forget and does not provide a reliable response mechanism.

        // 1. Convert the JavaScript object (sendPkg) to a JSON string.
        const jsonString = JSON.stringify(sendPkg);

        // 2. Wrap the JSON string in a Blob to specify the Content-Type.
        // This is necessary for the backend to correctly parse the request body as JSON.
        const beaconData = new Blob([jsonString], {
            type: 'application/json; charset=UTF-8'
        });

        // 3. Use navigator.sendBeacon to reliably send the data in the background.
        // The browser guarantees this request will be initiated before the page unloads.
        const success = navigator.sendBeacon(backEnd, beaconData);

    }


    return reveal;
}