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
 * AMD module for the coordinator's per-field review notes panel.
 *
 * Mirrors mod_syllabus/autosave (same debounce/status-indicator/rejection pattern), targeting
 * .syllabus-review-note-input instead of .syllabus-customfield-editor and calling
 * mod_syllabus_save_review_note instead of mod_syllabus_save_customfield_value.
 *
 * @module     mod_syllabus/review_notes
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';

/** @type {int} Course module ID. */
let cmid = 0;

/** @type {Object} One debounce timer per note element id. */
const debounceTimers = {};

/**
 * Shows the "saving..." status next to a note row.
 *
 * @param {HTMLElement} row The .syllabus-review-note-row container.
 */
const showSaving = (row) => {
    const status = row.querySelector('.syllabus-review-note-status');
    if (status) {
        status.classList.remove('d-none');
        status.textContent = status.dataset.saving;
    }
};

/**
 * Shows the "saved" status next to a note row, fading it out shortly after.
 *
 * @param {HTMLElement} row The .syllabus-review-note-row container.
 */
const showSaved = (row) => {
    const status = row.querySelector('.syllabus-review-note-status');
    if (status) {
        status.textContent = status.dataset.saved;
        setTimeout(() => status.classList.add('d-none'), 3000);
    }
};

/**
 * Shows a rejected save as a plain alert instead of Moodle's generic AJAX error dialog — every
 * rejection reaching this point is one of save_review_note's own moodle_exception messages
 * (invalid area/field) or a capability error, both already carrying a clear, translated
 * message (see mod_syllabus/autosave for the same rationale in full).
 *
 * @param {Error} error
 */
const showRejected = async(error) => {
    const title = await getString('actionnotallowed', 'mod_syllabus');
    Notification.alert(title, error.message);
};

/**
 * Saves (or clears, when empty) the current value of one review note via the web service.
 *
 * @param {HTMLTextAreaElement} textarea
 */
const doSave = async(textarea) => {
    const row = textarea.closest('.syllabus-review-note-row');
    showSaving(row);

    try {
        await Ajax.call([{
            methodname: 'mod_syllabus_save_review_note',
            args: {
                cmid,
                area: row.dataset.area,
                instanceid: parseInt(row.dataset.instanceid, 10),
                fieldid: parseInt(row.dataset.fieldid, 10),
                note: textarea.value,
            },
        }])[0];
        showSaved(row);
    } catch (error) {
        showRejected(error);
    }
};

/**
 * Handles input on a review note, debouncing the actual save.
 *
 * @param {Event} e
 */
const handleInput = (e) => {
    const textarea = e.target;
    const key = textarea.id;

    if (debounceTimers[key]) {
        clearTimeout(debounceTimers[key]);
    }
    debounceTimers[key] = setTimeout(() => doSave(textarea), 800);
};

/**
 * Initialize autosave for every coordinator review note on the page.
 *
 * @param {int} coursemoduleid Course module ID.
 */
export const init = (coursemoduleid) => {
    cmid = coursemoduleid;

    document.querySelectorAll('.syllabus-review-note-input').forEach((textarea) => {
        textarea.addEventListener('input', handleInput);
    });
};
