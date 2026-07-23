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
 * AMD module for autosaving the plan-level Characterisation and Presentation fields.
 *
 * @module     mod_syllabus/plan_details
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';

/** @type {int} Course module ID. */
let cmid = 0;

/**
 * Shows a rejected save as a plain alert instead of Moodle's generic AJAX error dialog —
 * same reasoning as the other AMD modules in this plugin (see weeks_manager.js).
 *
 * @param {Error} error
 */
const showRejected = async(error) => {
    const title = await getString('actionnotallowed', 'mod_syllabus');
    Notification.alert(title, error.message);
};

/**
 * Reads an `<input type="date">` element's value as a Unix timestamp, or null when empty.
 *
 * @param {HTMLElement} input
 * @returns {?int}
 */
const readTimestamp = (input) => {
    if (!input || !input.value) {
        return null;
    }
    return Math.floor(new Date(input.value).getTime() / 1000);
};

/**
 * Converts each `[data-timestamp]` date input's server-provided Unix timestamp into the
 * `YYYY-MM-DD` string the `date` input type expects as its value.
 *
 * @param {HTMLElement} container
 */
const hydrateDateInputs = (container) => {
    container.querySelectorAll('input[data-timestamp]').forEach((input) => {
        const timestamp = parseInt(input.dataset.timestamp, 10);
        if (!timestamp) {
            return;
        }
        input.value = new Date(timestamp * 1000).toISOString().slice(0, 10);
    });
};

/**
 * Saves the current values of the Characterisation/Presentation fields.
 *
 * @param {HTMLElement} container The .syllabus-characterisation-edit element.
 */
const save = async(container) => {
    const academicperiod = container.querySelector('.syllabus-plan-academicperiod').value.trim() || null;
    const coursestartdate = readTimestamp(container.querySelector('.syllabus-plan-coursestartdate'));
    const courseenddate = readTimestamp(container.querySelector('.syllabus-plan-courseenddate'));
    const totaldurationInput = container.querySelector('.syllabus-plan-totalduration').value;
    const totalduration = totaldurationInput === '' ? null : parseInt(totaldurationInput, 10);
    const presentationvideourl = container.querySelector('.syllabus-plan-presentationvideourl').value.trim() || null;

    try {
        await Ajax.call([{
            methodname: 'mod_syllabus_save_plan_details',
            args: {cmid, academicperiod, coursestartdate, courseenddate, totalduration, presentationvideourl},
        }])[0];
    } catch (error) {
        showRejected(error);
    }
};

/**
 * Initialize the Characterisation/Presentation autosave.
 *
 * @param {int} coursemoduleid Course module ID.
 */
export const init = (coursemoduleid) => {
    cmid = coursemoduleid;

    const container = document.querySelector('.syllabus-characterisation-edit');
    if (!container) {
        return;
    }

    hydrateDateInputs(container);
    container.querySelectorAll('input').forEach((input) => {
        input.addEventListener('change', () => save(container));
    });
};
