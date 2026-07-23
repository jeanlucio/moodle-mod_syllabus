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
 * AMD module for autosaving narrative Custom Field content in mod_syllabus.
 *
 * @module     mod_syllabus/autosave
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';

/** @type {int} Course module ID. */
let cmid = 0;

/** @type {Object} One debounce timer per field element id. */
const debounceTimers = {};

/**
 * Shows the "saving..." status next to a field.
 *
 * @param {HTMLElement} field The .syllabus-field container.
 */
const showSaving = (field) => {
    const status = field.querySelector('.syllabus-save-status');
    if (status) {
        status.classList.remove('d-none');
        status.textContent = status.dataset.saving;
    }
};

/**
 * Shows the "saved" status next to a field, fading it out shortly after.
 *
 * @param {HTMLElement} field The .syllabus-field container.
 */
const showSaved = (field) => {
    const status = field.querySelector('.syllabus-save-status');
    if (status) {
        status.textContent = status.dataset.saved;
        setTimeout(() => status.classList.add('d-none'), 3000);
    }
};

/**
 * Shows a rejected save as a plain alert instead of Moodle's generic AJAX error dialog. Every
 * rejection reaching this point is one of save_customfield_value's own moodle_exception
 * messages (invalid area/field) or a capability error — both already carry a clear, translated
 * message; Notification.exception() would instead show a raw error code as the dialog title
 * plus a stack trace, meant for genuinely unexpected failures, not an anticipated rejection.
 *
 * @param {Error} error
 */
const showRejected = async(error) => {
    const title = await getString('actionnotallowed', 'mod_syllabus');
    Notification.alert(title, error.message);
};

/**
 * Saves the current value of one field via the web service.
 *
 * @param {HTMLTextAreaElement} textarea
 */
const doSave = async(textarea) => {
    const field = textarea.closest('.syllabus-field');
    showSaving(field);

    try {
        await Ajax.call([{
            methodname: 'mod_syllabus_save_customfield_value',
            args: {
                cmid,
                area: textarea.dataset.area,
                instanceid: parseInt(textarea.dataset.instanceid, 10),
                fieldid: parseInt(textarea.dataset.fieldid, 10),
                value: textarea.value,
                itemid: parseInt(textarea.dataset.itemid, 10),
            },
        }])[0];
        showSaved(field);
    } catch (error) {
        showRejected(error);
    }
};

/**
 * Handles input on a narrative field, debouncing the actual save.
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
 * Initialize autosave for every narrative Custom Field editor on the page.
 *
 * @param {int} coursemoduleid Course module ID.
 */
export const init = (coursemoduleid) => {
    cmid = coursemoduleid;

    document.querySelectorAll('.syllabus-customfield-editor').forEach((textarea) => {
        textarea.addEventListener('input', handleInput);
    });
};
