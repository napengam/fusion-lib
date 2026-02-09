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
     * Style injection
     * =============================== */
    (function injectStyle() {
        if (document.getElementById('justDialogStyle')) {
            return;
        }

        const s = document.createElement('style');
        s.id = 'justDialogStyle';
        s.textContent = `
            .outerDialog {
                overflow: auto;
                resize: both;
                min-height: 10px;
                position: fixed;
                margin: 0;
                border: 0;
                max-width: 600px;
                min-width: 280px;
                box-shadow: 1px 2px 15px rgba(0,0,0,0.3);
                padding: 0;
                border-radius: 6px;
            }
            .outerDialog::backdrop {
                background: rgba(0, 0, 0, 0.4);
            }
            .diagDrag { cursor: grab; }
            .diagDrag:active { cursor: grabbing; }
            #fadeBox {
                position: fixed;
                top: 20px;
                right: 20px;
                transition: opacity 1.5s ease-out;
                z-index: 9999;
            }
            .fade-out { opacity: 0; }
            .dialog-footer {
                padding: 1rem;
                border-top: 1px solid #dbdbdb;
                background-color: #f9f9f9;
            }
        `;
        document.head.appendChild(s);
    })();

    /* ===============================
     * Internal Helpers
     * =============================== */
    function positionDialogShow(wrapper) {
        const dlg = wrapper.querySelector('dialog');
        if (dlg) {
            dlg.style.left = `${(window.innerWidth - dlg.offsetWidth) / 2}px`;
            dlg.style.top = `${(window.innerHeight - dlg.offsetHeight) / 2}px`;
        }
    }

    function makeDraggable(dialog, handle) {
        let offsetX, offsetY;
        handle.addEventListener('pointerdown', (e) => {
            offsetX = e.clientX - dialog.offsetLeft;
            offsetY = e.clientY - dialog.offsetTop;

            const move = (e) => {
                dialog.style.left = `${e.clientX - offsetX}px`;
                dialog.style.top = `${e.clientY - offsetY}px`;
            };

            const up = () => {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', up);
            };

            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
        });
    }

    const dialogFactory = (function () {
        const cache = new Map();

        function create(type, cfg) {
            if (cache.has(type)) {
                return cache.get(type);
            }

            const wrap = document.createElement('div');
            wrap.innerHTML = `
                <dialog class="outerDialog">
                    <div class="message is-dark" style="margin-bottom:0">
                        <div class="message-header diagDrag">
                            <span>${cfg.title}</span>
                            <button class="delete" data-action="close"></button>
                        </div>
                        <div class="message-body"></div>
                    </div>
                    <div class="dialog-footer">${cfg.footer || ''}</div>
                </dialog>`;

            document.body.appendChild(wrap);
            const dlg = wrap.querySelector('dialog');
            const body = dlg.querySelector('.message-body');

            dlg.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) {
                    return;
                }
                const action = btn.dataset.action;
                if (action === 'close') {
                    dlg.close();
                } else {
                    dlg.dispatchEvent(new CustomEvent('dialog-action', { detail: action }));
                }
            });

            makeDraggable(dlg, dlg.querySelector('.diagDrag'));
            const inst = { wrap, dlg, body };
            cache.set(type, inst);
            return inst;
        }

        function show(inst, { modal = true } = {}) {
            if (modal) {
                inst.dlg.showModal();
            } else {
                inst.dlg.show();
            }
            positionDialogShow(inst.wrap);
        }

        return { create, show };
    })();

    /* ===============================
     * Implementations
     * =============================== */

    function myAlert(text) {
        const d = dialogFactory.create('alert', {
            title: lt.alert,
            footer: `<button class="button is-link is-fullwidth" data-action="close">${lt.ok}</button>`
        });
        d.body.innerHTML = text;
        dialogFactory.show(d);
    }

    function myConfirm(text, yes, no) {
        const d = dialogFactory.create('confirm', {
            title: lt.confirm,
            footer: `
                <div class="buttons is-centered">
                    <button class="button is-primary" data-action="yes">${lt.yes}</button>
                    <button class="button" data-action="no">${lt.no}</button>
                </div>`
        });
        d.body.innerHTML = text;

        const h = (e) => {
            if (e.detail === 'yes') { yes?.(); }
            if (e.detail === 'no') { no?.(); }
            d.dlg.close();
        };
        d.dlg.addEventListener('dialog-action', h, { once: true });
        dialogFactory.show(d);
    }

    function myPrompt(text, defaultValue, save) {
        const d = dialogFactory.create('prompt', {
            title: lt.prompt,
            footer: `
                <div class="field">
                    <label class="label">${lt.value}</label>
                    <div class="control"><input class="input" name="v"></div>
                </div>
                <div class="buttons is-right">
                    <button class="button is-primary" data-action="save">${lt.save}</button>
                    <button class="button" data-action="close">${lt.cancel}</button>
                </div>`
        });
        d.body.innerHTML = text;
        const input = d.dlg.querySelector('[name=v]');
        input.value = defaultValue;

        const h = (e) => {
            if (e.detail === 'save') {
                save(input.value);
                d.dlg.close();
            }
        };
        d.dlg.addEventListener('dialog-action', h, { once: true });
        dialogFactory.show(d);
    }

    function myLogin(text, save) {
        const d = dialogFactory.create('login', {
            title: lt.login,
            footer: `
                <div class="field">
                    <label class="label">${lt.name}</label>
                    <div class="control">
                        <input class="input" name="u" autocomplete="username">
                    </div>
                </div>
                <div class="field">
                    <label class="label">${lt.passwd}</label>
                    <div class="control">
                        <input class="input" type="password" name="p" autocomplete="current-password">
                    </div>
                </div>
                <div class="buttons is-block">
                    <button class="button is-primary is-fullwidth" data-action="login">${lt.login}</button>
                    <button class="button is-light is-fullwidth mt-2" data-action="close">${lt.cancel}</button>
                </div>`
        });
        d.body.innerHTML = text;

        const h = (e) => {
            if (e.detail === 'login') {
                const u = d.dlg.querySelector('[name=u]').value;
                const p = d.dlg.querySelector('[name=p]').value;
                save(u, p);
                d.dlg.close();
            }
        };
        d.dlg.addEventListener('dialog-action', h, { once: true });
        dialogFactory.show(d);
    }

    function myUpload(text, actionUrl, responds, hiddenFields = {}) {
        const d = dialogFactory.create('upload', {
            title: lt.upload,
            footer: `
                <form method="POST" enctype="multipart/form-data" target="jd_upload_iframe">
                    <div class="field">
                        <div class="file has-name is-fullwidth">
                            <label class="file-label">
                                <input class="file-input" type="file" name="uploadedfile[]" multiple>
                                <span class="file-cta">
                                    <span class="file-label">Datei wählen…</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="buttons is-right mt-4">
                        <button class="button is-primary" type="submit">${lt.save}</button>
                        <button class="button" data-action="close">${lt.cancel}</button>
                    </div>
                </form>
                <iframe name="jd_upload_iframe" style="display:none"></iframe>`
        });

        d.body.innerHTML = text || '';
        const form = d.dlg.querySelector('form');
        const iframe = d.dlg.querySelector('iframe');
        form.action = actionUrl;

        // Clean and add hidden fields
        Array.from(form.querySelectorAll('input[type=hidden]')).forEach((n) => { n.remove(); });
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

    function myInform(text, fade = false) {
        const d = dialogFactory.create('inform', {
            title: lt.info,
            footer: `<button class="button is-small is-fullwidth" data-action="close">${lt.ok}</button>`
        });
        d.body.innerHTML = text;
        dialogFactory.show(d, { modal: false });

        if (fade) {
            d.wrap.id = 'fadeBox';
            d.wrap.classList.remove('fade-out');
            setTimeout(() => {
                d.wrap.classList.add('fade-out');
                setTimeout(() => {
                    d.dlg.close();
                    d.wrap.classList.remove('fade-out');
                }, 1500);
            }, 2000);
        }
    }

    // Public API Assignment
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