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
 * Adds a "Restore default text" button to each field row on managefields.php, next to the
 * core Custom Fields UI's own edit/delete buttons — that UI (core_customfield\output\
 * management, templates/list.mustache) is entirely core-owned, so this button is injected
 * client-side rather than added to a template this plugin does not control.
 *
 * @module     mod_syllabus/managefields_reset
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString, getStrings} from 'core/str';

/**
 * Shows a rejected reset as a plain alert instead of Moodle's generic AJAX error dialog —
 * same reasoning as the other AMD modules in this plugin (see weeks_manager.js).
 *
 * @param {Error} error
 */
const showRejected = async(error) => {
    const title = await getString('actionnotallowed', 'mod_syllabus');
    Notification.alert(title, error.message);
};

/**
 * Resets one field's description after confirmation.
 *
 * @param {int} fieldid
 * @param {HTMLElement} button
 */
const resetField = async(fieldid, button) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm(button.dataset.confirm)) {
        return;
    }
    try {
        await Ajax.call([{methodname: 'mod_syllabus_reset_field_description', args: {fieldid}}])[0];
        const message = await getString('fielddescriptionreset', 'mod_syllabus');
        Notification.addNotification({message, type: 'success'});
    } catch (error) {
        showRejected(error);
    }
};

/**
 * Finds every field row the core Custom Fields UI already rendered and injects a "Restore
 * default text" button next to its delete button — only where the core UI itself decided to
 * show edit/delete (canedit), so this never appears for a user without edit rights.
 */
export const init = async() => {
    const rows = document.querySelectorAll('tr.field[data-field-id]');
    if (!rows.length) {
        return;
    }

    const [label, confirmMessage] = await getStrings([
        {key: 'resetfielddescription', component: 'mod_syllabus'},
        {key: 'confirmresetfielddescription', component: 'mod_syllabus'},
    ]);

    rows.forEach((row) => {
        const fieldid = parseInt(row.dataset.fieldId, 10);
        const deleteButton = row.querySelector('[data-role="deletefield"]');
        if (!fieldid || !deleteButton) {
            return;
        }
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link btn-icon syllabus-reset-field-description';
        button.textContent = label;
        button.dataset.confirm = confirmMessage;
        button.addEventListener('click', () => resetField(fieldid, button));
        deleteButton.insertAdjacentElement('afterend', button);
    });
};
