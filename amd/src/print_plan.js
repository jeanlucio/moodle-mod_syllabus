// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Wires the "Print / Export PDF" button. Collapsed week fieldsets (native <details>) are never
 * included in the browser's print output while closed, so every one of them is forced open right
 * before print and restored to its prior state once the print dialog closes — the actual PDF
 * file, if any, is produced by the browser's own "Save as PDF" print destination, not by this
 * plugin.
 *
 * @module     mod_syllabus/print_plan
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let previousStates = null;

/**
 * Forces every week fieldset open, remembering each one's prior state.
 */
const expandAllWeeks = () => {
    previousStates = new Map();
    document.querySelectorAll('.syllabus-week-row').forEach((week) => {
        previousStates.set(week, week.open);
        week.open = true;
    });
};

/**
 * Restores every week fieldset to the state it had before printing.
 */
const restoreWeeks = () => {
    if (!previousStates) {
        return;
    }
    previousStates.forEach((wasopen, week) => {
        week.open = wasopen;
    });
    previousStates = null;
};

/**
 * Wires the print button, if present on the current tab.
 */
export const init = () => {
    const button = document.querySelector('.syllabus-print-button');
    if (!button) {
        return;
    }

    button.addEventListener('click', () => {
        expandAllWeeks();
        window.print();
    });
    window.addEventListener('afterprint', restoreWeeks);
};
