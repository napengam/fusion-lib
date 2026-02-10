function toolTip(options = {}) {
    const {
        id = 'tip0815',
        delay = 300,
        padding = 8,
        offset = 10
    } = options;

    let hideTimer = null;
    let activeTarget = null;

    const tip = document.getElementById(id) || createTip();

    function createTip() {
        const el = document.createElement('div');
        el.id = id;
        Object.assign(el.style, {
            position: 'absolute',
            maxWidth: '320px',
            background: 'aliceblue',
            border: '1px solid #333',
            borderRadius: '6px',
            padding: '6px 8px',
            fontSize: '0.85em',
            lineHeight: '1.3',
            boxShadow: '0 2px 10px rgba(0,0,0,0.25)',
            display: 'none',
            pointerEvents: 'none',
            zIndex: highestZIndex()
        });
        document.body.appendChild(el);
        return el;
    }

    function bind(elem) {
        if (!elem.dataset.tooltip) {
            elem.dataset.tooltip = elem.title;
            elem.removeAttribute('title');
        }

        elem.addEventListener('mouseenter', show);
        elem.addEventListener('mouseleave', scheduleHide);
        elem.addEventListener('click', hide);
    }

    function show(e) {
        clearTimeout(hideTimer);

        const text = this.dataset.tooltip;
        if (!text) {
            return;
        }

        activeTarget = this;
        if (/<br\s*\/?>/i.test(text)) {
            tip.innerHTML = text;
        } else {
            tip.textContent = text;
        }
        tip.style.display = 'block';

        positionTip(this);
    }

    function scheduleHide() {
        hideTimer = setTimeout(hide, delay);
    }

    function hide() {
        clearTimeout(hideTimer);
        tip.style.display = 'none';
        activeTarget = null;
    }

    function positionTip(target) {
        const tr = target.getBoundingClientRect();
        const tw = tip.offsetWidth;
        const th = tip.offsetHeight;

        const vw = window.innerWidth;
        const vh = window.innerHeight;

        const placements = [
            {// top
                x: tr.left + tr.width / 2 - tw / 2,
                y: tr.top - th - offset
            },
            {// bottom
                x: tr.left + tr.width / 2 - tw / 2,
                y: tr.bottom + offset
            },
            {// right
                x: tr.right + offset,
                y: tr.top + tr.height / 2 - th / 2
            },
            {// left
                x: tr.left - tw - offset,
                y: tr.top + tr.height / 2 - th / 2
            }
        ];

        let pos = placements.find(p =>
            p.x >= padding &&
                    p.y >= padding &&
                    p.x + tw <= vw - padding &&
                    p.y + th <= vh - padding
        ) || placements[1]; // fallback: bottom

        // Clamp as last safety net
        pos.x = Math.min(Math.max(padding, pos.x), vw - tw - padding);
        pos.y = Math.min(Math.max(padding, pos.y), vh - th - padding);

        tip.style.left = `${pos.x + window.scrollX}px`;
        tip.style.top = `${pos.y + window.scrollY}px`;
    }

    function highestZIndex() {
        let max = 100;
        document.querySelectorAll('body *').forEach(el => {
            const z = parseInt(getComputedStyle(el).zIndex, 10);
            if (!isNaN(z) && z > max) {
                max = z;
            }
        });
        return max + 1;
    }

    // Initial binding
    document.querySelectorAll('[title]').forEach(bind);

    return {
        addTip: bind,
        closeTip: hide
    };
}
