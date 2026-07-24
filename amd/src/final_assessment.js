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
 * AMD module for autosaving the plan-level Final assessment block.
 *
 * Dispatches a `syllabus-autosave` CustomEvent on `document` around each save — same contract
 * plan_details.js uses for the Characterisation fields.
 *
 * @module     mod_syllabus/final_assessment
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';
import {readTimestamp} from './plan_dateselect';

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
 * Saves the current values of the Final assessment fields.
 *
 * @param {HTMLElement} container The .syllabus-finalassessment-edit element.
 */
const save = async(container) => {
    const finalassessmenttitle = container.querySelector('.syllabus-plan-finalassessmenttitle').value.trim() || null;
    const finalassessmenttype = container.querySelector('.syllabus-plan-finalassessmenttype').value.trim() || null;
    const finalassessmentstartdate = readTimestamp(container.querySelector('.syllabus-plan-finalassessmentstartdate'));
    const finalassessmentenddate = readTimestamp(container.querySelector('.syllabus-plan-finalassessmentenddate'));
    const pointsInput = container.querySelector('.syllabus-plan-finalassessmentpoints').value;
    const finalassessmentpoints = pointsInput === '' ? null : parseFloat(pointsInput);

    document.dispatchEvent(new CustomEvent('syllabus-autosave', {detail: {pending: true}}));
    try {
        await Ajax.call([{
            methodname: 'mod_syllabus_save_final_assessment',
            args: {
                cmid, finalassessmenttitle, finalassessmenttype,
                finalassessmentstartdate, finalassessmentenddate, finalassessmentpoints,
            },
        }])[0];
    } catch (error) {
        showRejected(error);
    } finally {
        document.dispatchEvent(new CustomEvent('syllabus-autosave', {detail: {pending: false}}));
    }
};

/**
 * Initialize the Final assessment autosave.
 *
 * @param {int} coursemoduleid Course module ID.
 */
export const init = (coursemoduleid) => {
    cmid = coursemoduleid;

    const container = document.querySelector('.syllabus-finalassessment-edit');
    if (!container) {
        return;
    }

    container.querySelectorAll('input, select').forEach((field) => {
        field.addEventListener('change', () => save(container));
    });
};
