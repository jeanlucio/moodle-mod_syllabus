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
 * AMD module for adding/editing/removing weeks and activities in a syllabus plan.
 *
 * @module     mod_syllabus/weeks_manager
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/** @type {int} Course module ID. */
let cmid = 0;

/**
 * Reloads the page after a successful structural change, mirroring mod_reflect's own
 * question-manager convention: simplest way to reflect a new/removed row and its narrative
 * field editors (each needs a fresh server-prepared draft file area) without hand-rolling a
 * client-side re-render.
 */
const reload = () => {
    window.location.reload();
};

/**
 * Saves a week (new or existing) from its row's current input values.
 *
 * @param {HTMLElement} row The .syllabus-week-row element.
 */
const saveWeek = async(row) => {
    const weekid = parseInt(row.dataset.weekid, 10) || 0;
    const title = row.querySelector('.syllabus-week-title').value.trim();
    const durationInput = row.querySelector('.syllabus-week-duration').value;
    const duration = durationInput === '' ? null : parseInt(durationInput, 10);

    if (!title) {
        row.querySelector('.syllabus-week-title').focus();
        return;
    }

    try {
        await Ajax.call([{
            methodname: 'mod_syllabus_save_week',
            args: {cmid, weekid, title, duration, startdate: null, enddate: null},
        }])[0];
        reload();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Deletes a week after confirmation.
 *
 * @param {HTMLElement} row The .syllabus-week-row element.
 */
const deleteWeek = async(row) => {
    const weekid = parseInt(row.dataset.weekid, 10);
    const button = row.querySelector('.syllabus-delete-week');
    // eslint-disable-next-line no-alert
    if (!weekid || !window.confirm(button.dataset.confirm)) {
        return;
    }

    try {
        await Ajax.call([{methodname: 'mod_syllabus_delete_week', args: {cmid, weekid}}])[0];
        reload();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Saves an activity (new or existing) from its row's current input values.
 *
 * @param {HTMLElement} row The .syllabus-activity-row element.
 */
const saveActivity = async(row) => {
    const activityid = parseInt(row.dataset.activityid, 10) || 0;
    const weekid = parseInt(row.dataset.weekid, 10);
    const title = row.querySelector('.syllabus-activity-title').value.trim();
    const type = row.querySelector('.syllabus-activity-type').value.trim() || null;
    const category = row.querySelector('.syllabus-activity-category').value.trim() || null;
    const pointsInput = row.querySelector('.syllabus-activity-points').value;
    const points = pointsInput === '' ? null : parseFloat(pointsInput);

    if (!title) {
        row.querySelector('.syllabus-activity-title').focus();
        return;
    }

    try {
        await Ajax.call([{
            methodname: 'mod_syllabus_save_activity',
            args: {cmid, weekid, activityid, title, type, category, startdate: null, enddate: null, points},
        }])[0];
        reload();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Deletes an activity after confirmation.
 *
 * @param {HTMLElement} row The .syllabus-activity-row element.
 */
const deleteActivity = async(row) => {
    const activityid = parseInt(row.dataset.activityid, 10);
    const button = row.querySelector('.syllabus-delete-activity');
    // eslint-disable-next-line no-alert
    if (!activityid || !window.confirm(button.dataset.confirm)) {
        return;
    }

    try {
        await Ajax.call([{methodname: 'mod_syllabus_delete_activity', args: {cmid, activityid}}])[0];
        reload();
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Builds a blank week row for adding a new week.
 *
 * @returns {HTMLElement}
 */
const buildNewWeekRow = () => {
    const row = document.createElement('div');
    row.className = 'syllabus-week-row border rounded p-3 mb-3';
    row.dataset.weekid = '0';
    row.innerHTML = `
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <input type="text" class="form-control syllabus-week-title">
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control syllabus-week-duration">
            </div>
            <div class="col-md-3 text-end">
                <button type="button" class="btn btn-sm btn-primary syllabus-save-week"></button>
            </div>
        </div>
    `;
    return row;
};

/**
 * Builds a blank activity row for adding a new activity to a given week.
 *
 * @param {int} weekid
 * @returns {HTMLElement}
 */
const buildNewActivityRow = (weekid) => {
    const row = document.createElement('div');
    row.className = 'syllabus-activity-row border rounded p-2 mb-2';
    row.dataset.activityid = '0';
    row.dataset.weekid = String(weekid);
    row.innerHTML = `
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" class="form-control syllabus-activity-title">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control syllabus-activity-type">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control syllabus-activity-category">
            </div>
            <div class="col-md-2">
                <input type="number" step="0.01" class="form-control syllabus-activity-points">
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-sm btn-primary syllabus-save-activity"></button>
            </div>
        </div>
    `;
    return row;
};

/**
 * Initialize the weeks/activities management module.
 *
 * @param {int} coursemoduleid Course module ID.
 */
export const init = (coursemoduleid) => {
    cmid = coursemoduleid;

    const container = document.querySelector('.path-mod-syllabus');
    if (!container) {
        return;
    }

    const addWeekBtn = container.querySelector('.syllabus-add-week');
    if (addWeekBtn) {
        addWeekBtn.addEventListener('click', () => {
            const row = buildNewWeekRow();
            row.querySelector('.syllabus-save-week').textContent = addWeekBtn.textContent.trim();
            container.querySelector('.syllabus-weeks-list').appendChild(row);
            row.querySelector('.syllabus-week-title').focus();
        });
    }

    // Delegated click handling: rows for existing weeks/activities are server-rendered,
    // new ones are appended dynamically above — one listener covers both.
    container.addEventListener('click', (e) => {
        const saveWeekBtn = e.target.closest('.syllabus-save-week');
        if (saveWeekBtn) {
            saveWeek(saveWeekBtn.closest('.syllabus-week-row'));
            return;
        }

        const deleteWeekBtn = e.target.closest('.syllabus-delete-week');
        if (deleteWeekBtn) {
            deleteWeek(deleteWeekBtn.closest('.syllabus-week-row'));
            return;
        }

        const saveActivityBtn = e.target.closest('.syllabus-save-activity');
        if (saveActivityBtn) {
            saveActivity(saveActivityBtn.closest('.syllabus-activity-row'));
            return;
        }

        const deleteActivityBtn = e.target.closest('.syllabus-delete-activity');
        if (deleteActivityBtn) {
            deleteActivity(deleteActivityBtn.closest('.syllabus-activity-row'));
            return;
        }

        const addActivityBtn = e.target.closest('.syllabus-add-activity');
        if (addActivityBtn) {
            const weekid = parseInt(addActivityBtn.dataset.weekid, 10);
            const row = buildNewActivityRow(weekid);
            row.querySelector('.syllabus-save-activity').textContent = addActivityBtn.textContent.trim();
            addActivityBtn.closest('.syllabus-week-row').querySelector('.syllabus-activities-list').appendChild(row);
            row.querySelector('.syllabus-activity-title').focus();
        }
    });
};
