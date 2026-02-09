/**
 * Table filtering module with pattern matching support.
 */
function filterTableF() {
    'use strict';
    /**
     * Main search function triggered by input events.
     * @param {string} filterRowId - The ID of the TR containing the filter inputs.
     * @param {Event} event - The triggering DOM event.
     */
    function searchRows(filterRowId, event) {
        const filterRow = document.getElementById(filterRowId);
        if (!filterRow) {
            return;
        }

        const inputs = filterRow.querySelectorAll('input');
        const eTable = filterRow.closest('table');
        const tBody = eTable.tBodies[0];

        // Hide table to prevent layout thrashing during manipulation
        eTable.style.display = 'none';

        // Prepare filter data once to avoid repeated DOM access in loops
        const activeFilters = [];
        inputs.forEach((input, index) => {
            const val = input.value.trim().toLowerCase();
            if (val !== '') {
                activeFilters.push({index: index, value: val});
            }
        });

        // Iterate through rows once (O(n) complexity)
        Array.from(tBody.rows).forEach((row) => {
            let isVisible = true;

            // Check every active filter against this row
            for (let filter of activeFilters) {
                const cell = row.cells[filter.index];
                const content = cell ? cell.innerText.toLowerCase() : '';

                if (!matchesPattern(content, filter.value)) {
                    isVisible = false;
                    break; // Stop checking other filters for this row
                }
            }

            row.style.display = isVisible ? '' : 'none';
        });

        // Restore table rendering
        eTable.style.display = '';

        // UI Updates: Scroll to top and update count
        window.scrollTo({
            top: 0,
            behavior: 'instant'
        });

        updateCounter(filterRowId, tBody);
    }

    /**
     * Pattern matcher for wildcards (*word*), prefixes (word*), and negation (!word).
     */
    function matchesPattern(str, pattern) {
        if (pattern === '') {
            return true;
        }

        if (pattern.startsWith("*") && pattern.endsWith("*")) {
            // Case: *word* -> Contains
            return str.includes(pattern.slice(1, -1));
        } else if (pattern.endsWith("*")) {
            // Case: word* -> Starts with
            return str.startsWith(pattern.slice(0, -1));
        } else if (pattern.startsWith("!")) {
            // Case: !word -> Not containing
            return !str.includes(pattern.slice(1));
        } else {
            // Default: Match against the first word only
            const firstWord = str.split(/\s+/)[0];
            return firstWord.startsWith(pattern);
        }
    }

    /**
     * Updates the UI counter showing visible row count.
     */
    function updateCounter(id, tBody) {
        const visibleRows = Array.from(tBody.rows).filter((r) => {
            return r.style.display !== 'none';
        });

        const counterElem = document.getElementById('n' + id);
        if (counterElem) {
            counterElem.innerHTML = '( ' + visibleRows.length + ' )';
        }
    }

    /**
     * Toggles visibility of the filter row.
     */
    function filterOnOff(id, forceHide = false) {
        const obj = document.getElementById(id);
        if (!obj) {
            return;
        }

        if (forceHide) {
            obj.style.display = 'none';
        } else {
            obj.style.display = (obj.style.display === 'none') ? '' : 'none';
    }
    }

    return {
        searchRows: searchRows,
        filterOnOff: filterOnOff
    };
}

const filterTable = filterTableF();