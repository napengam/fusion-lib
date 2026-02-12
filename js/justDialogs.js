function justDialogs(language = 'de') {

    if (justDialogs._instance) {
        return justDialogs._instance;
    }

    /* ===============================
     * Localization
     * =============================== */
    const LTEXT = {
        de: {
            alert: 'Alarm',
            confirm: 'Bestätigung',
            prompt: 'Eingabe',
            info: 'Information',
            yes: 'Ja',
            no: 'Nein',
            cancel: 'Abbruch',
            save: 'Sichern',
            ok: 'Ok',
            passwd: 'Passwort',
            login: 'Anmelden',
            name: 'Benutzername',
            value: 'Wert',
            upload: 'Datei Upload'
        },
        en: {
            alert: 'Alert',
            confirm: 'Confirmation',
            prompt: 'Prompt',
            info: 'Information',
            yes: 'Yes',
            no: 'No',
            cancel: 'Cancel',
            save: 'Save',
            ok: 'Ok',
            passwd: 'Password',
            login: 'Login',
            name: 'Username',
            value: 'Value',
            upload: 'File Upload'
        }
    };

    const lt = LTEXT[language] || LTEXT.de;

    /* ===============================
     * Internal helpers
     * =============================== */
    function centerDialog(dlg) {
        dlg.style.left = `${(window.innerWidth - dlg.offsetWidth) / 2}px`;
        dlg.style.top = `${(window.innerHeight - dlg.offsetHeight) / 2}px`;
    }

    function makeDraggable(dialog, handle) {
        let startX = 0;
        let startY = 0;
        let startLeft = 0;
        let startTop = 0;

        handle.style.cursor = 'grab';

        handle.addEventListener('pointerdown', (e) => {
            e.preventDefault();

            const rect = dialog.getBoundingClientRect();

            // Force fixed positioning so browser stops auto-centering
            dialog.style.position = 'fixed';
            dialog.style.margin = '0';

            startX = e.clientX;
            startY = e.clientY;
            startLeft = rect.left;
            startTop = rect.top;

            handle.style.cursor = 'grabbing';
            handle.setPointerCapture(e.pointerId);

            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
        });

        function onMove(e) {
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            dialog.style.left = `${startLeft + dx}px`;
            dialog.style.top = `${startTop + dy}px`;
        }

        function onUp(e) {
            handle.releasePointerCapture(e.pointerId);
            handle.style.cursor = 'grab';

            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
        }
    }


    /* ===============================
     * Dialog factory
     * =============================== */
    const dialogFactory = (function () {
        const cache = new Map();

        function create(type, cfg) {
            if (cache.has(type)) {
                return cache.get(type);
            }

            const wrap = document.createElement('div');
            wrap.className = 'fusion-dialog-wrap';

            wrap.innerHTML = `
                <dialog class="fusion-dialog">
                    <div class="fusion-dialog-header fusion-dialog-drag">
                        <span class="fusion-dialog-title">${cfg.title}</span>
                        <button class="fusion-dialog-close" data-action="close">×</button>
                    </div>
                    <div class="fusion-dialog-body"></div>
                    <div class="fusion-dialog-footer">
                        ${cfg.footer || ''}
                    </div>
                </dialog>
            `;

            document.body.appendChild(wrap);

            const dlg = wrap.querySelector('dialog');
            const body = wrap.querySelector('.fusion-dialog-body');

            dlg.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) {
                    return;
                }
                const action = btn.dataset.action;
                if (action === 'close') {
                    dlg.close();
                } else {
                    dlg.dispatchEvent(
                            new CustomEvent('dialog-action', {detail: action})
                            );
                }
            });

            makeDraggable(dlg, dlg.querySelector('.fusion-dialog-drag'));

            const inst = {wrap, dlg, body};
            cache.set(type, inst);
            return inst;
        }

        function show(inst, modal = true) {
            if (modal) {
                inst.dlg.showModal();
            } else {
                inst.dlg.show();
            }
            centerDialog(inst.dlg);
        }

        return {create, show};
    })();

    /* ===============================
     * Public dialog APIs
     * =============================== */

    function myAlert(text,hook=false) {
        const d = dialogFactory.create('alert', {
            title: lt.alert,
            footer: `<button class="fusion-btn fusion-btn-primary" data-action="close">${lt.ok}</button>`
        });
        d.body.innerHTML = text;
        dialogFactory.show(d);
        if (typeof hook === 'function') {
            beforeCloseHook = hook;
        }
    }

    function myConfirm(text, yes, no) {
        const d = dialogFactory.create('confirm', {
            title: lt.confirm,
            footer: `
                <button class="fusion-btn fusion-btn-primary" data-action="yes">${lt.yes}</button>
                <button class="fusion-btn" data-action="no">${lt.no}</button>
            `
        });

        d.body.innerHTML = text;

        d.dlg.addEventListener('dialog-action', (e) => {
            if (e.detail === 'yes') {
                yes?.();
            }
            if (e.detail === 'no') {
                no?.();
            }
            d.dlg.close();
        }, {once: true});

        dialogFactory.show(d);
    }

    function myPrompt(text, defaultValue, save) {
        const d = dialogFactory.create('prompt', {
            title: lt.prompt,
            footer: `
                <button class="fusion-btn fusion-btn-primary" data-action="save">${lt.save}</button>
                <button class="fusion-btn" data-action="close">${lt.cancel}</button>
            `
        });

        d.body.innerHTML = `
            <label class="fusion-label">${lt.value}</label>
            <input class="fusion-input" name="v">
        `;

        const input = d.dlg.querySelector('[name=v]');
        input.value = defaultValue;

        d.dlg.addEventListener('dialog-action', (e) => {
            if (e.detail === 'save') {
                save(input.value);
                d.dlg.close();
            }
        }, {once: true});

        dialogFactory.show(d);
    }

    function myLogin(text, save) {
        const d = dialogFactory.create('login', {
            title: lt.login,
            footer: `
                <button class="fusion-btn fusion-btn-primary" data-action="login">${lt.login}</button>
                <button class="fusion-btn" data-action="close">${lt.cancel}</button>
            `
        });

        d.body.innerHTML = `
            <label class="fusion-label">${lt.name}</label>
            <input class="fusion-input" name="u" autocomplete="username">
            <label class="fusion-label">${lt.passwd}</label>
            <input class="fusion-input" type="password" name="p" autocomplete="current-password">
        `;

        d.dlg.addEventListener('dialog-action', (e) => {
            if (e.detail === 'login') {
                save(
                        d.dlg.querySelector('[name=u]').value,
                        d.dlg.querySelector('[name=p]').value
                        );
                d.dlg.close();
            }
        }, {once: true});

        dialogFactory.show(d);
    }

    function myInform(text, fade = false) {
        const d = dialogFactory.create('inform', {
            title: lt.info,
            footer: `<button class="fusion-btn" data-action="close">${lt.ok}</button>`
        });

        d.body.innerHTML = text;
        dialogFactory.show(d, false);

        if (fade) {
            d.wrap.classList.add('fusion-fade');
            setTimeout(() => d.dlg.close(), 3500);
    }
    }
    function myUpload(text, actionUrl, responds, hiddenFields = {}) {
        const d = dialogFactory.create('upload', {
            title: lt.upload,
            footer: `
                                <form method="POST"
                       enctype="multipart/form-data"
                       target="jd_upload_iframe"
                       class="fusion-form">

                     <div class="fusion-field">
                         <label class="fusion-file">
                             <input class="fusion-file-input"
                                    type="file"
                                    name="uploadedfile[]"
                                    multiple>
                             <span class="fusion-file-label">Datei wählen…</span>
                         </label>
                     </div>

                     <div class="fusion-actions fusion-actions-right">
                         <button class="fusion-btn fusion-btn-primary" type="submit">
                             ${lt.save}
                         </button>
                         <button class="fusion-btn" data-action="close">
                             ${lt.cancel}
                         </button>
                     </div>
                 </form>

                 <iframe name="jd_upload_iframe" class="fusion-hidden"></iframe>
`
        });

        d.body.innerHTML = text || '';
        const form = d.dlg.querySelector('form');
        const iframe = d.dlg.querySelector('iframe');
        form.action = actionUrl;

        // Clean and add hidden fields
        Array.from(form.querySelectorAll('input[type=hidden]')).forEach((n) => {
            n.remove();
        });
        Object.entries(hiddenFields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });

        iframe.onload = () => {
            try {
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                const responseText = doc.body ? doc.body.textContent.trim() : "";
                if (responseText) {
                    responds?.(responseText);
                    d.dlg.close();
                }
            } catch (err) {
                console.error('Upload response access denied', err);
            }
        };

        dialogFactory.show(d);
    }
    /* ===============================
     * Public API
     * =============================== */
    justDialogs._instance = {
        myAlert,
        myConfirm,
        myPrompt,
        myLogin,
        myInform,
        myUpload
    };

    return justDialogs._instance;
}
