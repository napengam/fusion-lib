function validator(value, foptions, lang = 'en') {
    'use strict';

    // =====================================================
    // Localized message dictionary
    // =====================================================
    const TEXTS = {
        en: {
            yearNotNumber: 'Year is not a number',
            yearRange: 'Year should be positive',
            monthNotNumber: 'Month is not a number',
            monthRange: 'Month must be between 1 and 12',
            dayNotNumber: 'Day is not a number',
            dayRange: (max) => `Day must be between 1 and ${max}`,
            notDate: 'Not a valid date',
            notNumber: 'Please enter a valid number',
            invalidTime: 'Time format must be HH:MM[:SS] or HHMM or HHMMSS',
            hourRange: 'Hour must be between 0 and 23',
            minuteRange: 'Minute must be between 0 and 59',
            secondRange: 'Second must be between 0 and 59',
            invalidEmail: 'Invalid email syntax',
            emailDomainBlocked: 'Domain not allowed',
            invalidUrl: 'Invalid URL',
            invalidField: 'Invalid field value'
        },
        de: {
            yearNotNumber: 'Jahr ist keine Zahl',
            yearRange: 'Jahr sollte positiv sein',
            monthNotNumber: 'Monat ist keine Zahl',
            monthRange: 'Monat muss zwischen 1 und 12 liegen',
            dayNotNumber: 'Tag ist keine Zahl',
            dayRange: (max) => `Tag muss zwischen 1 und ${max} liegen`,
            notDate: 'Kein gültiges Datum',
            notNumber: 'Bitte eine gültige Zahl eingeben',
            invalidTime: 'Zeitformat muss HH:MM[:SS] oder HHMM oder HHMMSS sein',
            hourRange: 'Stunde muss zwischen 0 und 23 liegen',
            minuteRange: 'Minute muss zwischen 0 und 59 liegen',
            secondRange: 'Sekunde muss zwischen 0 und 59 liegen',
            invalidEmail: 'Ungültige E-Mail-Syntax',
            emailDomainBlocked: 'Domain nicht erlaubt',
            invalidUrl: 'Keine gültige URL',
            invalidField: 'Ungültiger Feldwert'
        }
    };

    const T = TEXTS[lang] || TEXTS.en;

    // =====================================================
    // Setup Validation Control Block
    // =====================================================
    const vcb = {
        ok: true,
        msg: '',
        value: '',
        reformated_value: false,
        value_reformated: ''
    };

    if (!foptions || typeof foptions.type !== 'string') {
        vcb.ok = false;
        vcb.msg = T.invalidField;
        return vcb;
    }

    let this_value = (value ?? '').toString().trim();
    const ty = foptions.type.toLowerCase();

    vcb.value = this_value;
    vcb.value_reformated = this_value;

    switch (ty) {
        case 'date': {
            if (this_value === '') {
                vcb.reformated_value = true;
                vcb.value_reformated = '0000-00-00';
                break;
            }

            if (this_value.includes('.')) {
                const arr = this_value.split('.');
                if (arr.length !== 3) {
                    vcb.ok = false;
                    vcb.msg = T.notDate;
                    break;
                }
                this_value = `${arr[2]}-${arr[1]}-${arr[0]}`;
                vcb.reformated_value = true;
                vcb.value_reformated = this_value;
            }

            const parts = this_value.split('-');
            if (!isDate(parts[0], parts[1], parts[2], vcb)) {
                vcb.value_reformated = this_value;
            }
            break;
        }

        case 'number': {
            if (this_value === '') {
                this_value = '0';
                vcb.value_reformated = this_value;
                break;
            }

            const v = this_value.split(',');
            if (v.length === 2) {
                this_value = `${v[0]}.${v[1]}`;
                vcb.reformated_value = true;
                vcb.value_reformated = this_value;
            }

            if (isNaN(this_value)) {
                vcb.ok = false;
                vcb.msg = T.notNumber;
                vcb.value = this_value;
            }
            break;
        }

        case 'time': {
            if (!isTime(this_value, vcb)) {
                vcb.ok = false;
            }
            break;
        }

        case 'email': {
            isEmail(this_value, vcb);
            break;
        }

        case 'url': {
            isValidUrl(this_value, vcb);
            break;
        }

        default:
            vcb.ok = true;
            break;
    }

    return vcb;

    // =====================================================
    // Helper Validators
    // =====================================================

    function isEmail(value, vcb) {
        const pattern = /^[a-zA-Z0-9_.-]+@[a-zA-Z0-9_.-]+\.[a-zA-Z]{2,}$/;
        if (value.length === 0) {
            return;
        }

        if (pattern.test(value)) {
            if (value.includes('@hgsweb.de') || value.includes('@athos-calling.com')) {
                vcb.ok = false;
                vcb.msg = T.emailDomainBlocked;
            }
        } else {
            vcb.ok = false;
            vcb.msg = T.invalidEmail;
        }
    }

    function isDate(yy, mm, dd, vcb) {
        const monthDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        const msg = [];
        let nyy = parseInt(yy, 10);

        if (isNaN(nyy)) {
            msg.push(T.yearNotNumber);
        } else {
            if (nyy < 100 && nyy >= 0) {
                nyy += 2000;
            }
            if ((nyy % 4 === 0 && nyy % 100 !== 0) || nyy % 400 === 0) {
                monthDays[1] = 29;
            }
        }

        const nmm = parseInt(mm, 10);
        const ndd = parseInt(dd, 10);
        let nddmax = 0;

        if (isNaN(nmm)) {
            msg.push(T.monthNotNumber);
        } else if (inRange(nmm, 1, 12)) {
            nddmax = monthDays[nmm - 1];
        } else {
            msg.push(T.monthRange);
        }

        if (isNaN(ndd)) {
            msg.push(T.dayNotNumber);
        } else if (nddmax > 0 && !inRange(ndd, 1, nddmax)) {
            msg.push(T.dayRange(nddmax));
        }

        if (msg.length > 0) {
            vcb.ok = false;
            vcb.msg = msg.join('\n');
            return false;
        }
        return true;
    }

    function isTime(value, vcb) {
        let h = 0, m = 0, s = 0;

        if (value === '') {
            vcb.reformated_value = true;
            vcb.value_reformated = '00:00:00';
            return true;
        }

        const numericOnly = /^[0-9]+$/;
        if (numericOnly.test(value)) {
            if (value.length === 4) {
                h = value.substring(0, 2);
                m = value.substring(2, 4);
                s = '00';
            } else if (value.length === 6) {
                h = value.substring(0, 2);
                m = value.substring(2, 4);
                s = value.substring(4, 6);
            } else {
                vcb.ok = false;
                vcb.msg = T.invalidTime;
                vcb.value_reformated = '00:00:00';
                return false;
            }
        } else if (value.includes(':')) {
            const parts = value.split(':');
            [h, m, s] = [parts[0] ?? '00', parts[1] ?? '00', parts[2] ?? '00'];
        } else {
            vcb.ok = false;
            vcb.msg = T.invalidTime;
            return false;
        }

        const errors = [];
        if (!inRange(h, 0, 23)) {
            errors.push(T.hourRange);
        }
        if (!inRange(m, 0, 59)) {
            errors.push(T.minuteRange);
        }
        if (!inRange(s, 0, 59)) {
            errors.push(T.secondRange);
        }

        if (errors.length > 0) {
            vcb.ok = false;
            vcb.msg = errors.join('\n');
            return false;
        }

        vcb.reformated_value = true;
        vcb.value_reformated = `${pad(h)}:${pad(m)}:${pad(s)}`;
        return true;
    }

    function isValidUrl(url, vcb) {
        url = url.trim();
        if (url === '') {
            return true;
        }

        const urlPattern = /^(https?|ftp):\/\/([a-z0-9.-]+\.[a-z]{2,4}|localhost)(\/[^\s<>"#%{}|\\^~[\]`]*)?$/i;
        if (!urlPattern.test(url)) {
            vcb.ok = false;
            vcb.msg = T.invalidUrl;
            return false;
        }
        return true;
    }

    function inRange(value, min, max) {
        const num = parseInt(value, 10);
        return !isNaN(num) && num >= min && num <= max;
    }

    function pad(v) {
        return v.toString().padStart(2, '0');
    }
}
