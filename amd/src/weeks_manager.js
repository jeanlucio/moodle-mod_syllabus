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
 * Existing rows autosave their structural fields on `change` (no per-row "Save" button, only
 * "Delete") — the same pattern amd/src/plan_details.js already uses for the Characterisation
 * fields, and plan_navigator.js's own delegated `change` listener already recomputes the
 * totals bar from the same events, so no extra coupling is needed. Adding a week/activity
 * creates the row on the server immediately, with a default title, then reloads the page with
 * the full fieldset open and scrolled into view — replacing the old flow where the "Add"
 * button only revealed a client-built blank row that still needed its own save click
 * (Fase 5.6.a, after live user feedback on the friction of the previous flow). A plan with
 * zero weeks goes one step further: init() creates the first week automatically, so the
 * professor never faces a page with nothing but a lone "Add week" button.
 *
 * @module     mod_syllabus/weeks_manager
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';
import {readTimestamp as readDateSelectTimestamp} from './plan_dateselect';

/** @type {int} Course module ID. */
let cmid = 0;

/** @type {string} sessionStorage key holding the id of a just-created week to scroll/focus. */
const FOCUS_WEEK_KEY = 'syllabus-focus-week';

/** @type {string} sessionStorage key holding the id of a just-created activity to scroll/focus. */
const FOCUS_ACTIVITY_KEY = 'syllabus-focus-activity';

/**
 * Reloads the page after a structural change that needs a fresh server render — creating a
 * row (its narrative field editors need a server-prepared draft file area) or deleting one.
 */
const reload = () => {
    window.location.reload();
};

/**
 * Shows a rejected structural write as a plain alert instead of Moodle's generic AJAX error
 * dialog. Every rejection reaching this point is either plan_state_manager's own
 * moodle_exception (e.g. "structuraleditblocked" while the plan awaits review) or a
 * capability error — both already carry a clear, translated message; Notification.exception()
 * would instead show a raw error code as the dialog title plus a stack trace, which is meant
 * for genuinely unexpected failures, not an outcome the workflow itself anticipates.
 *
 * @param {Error} error
 */
const showRejected = async(error) => {
    const title = await getString('actionnotallowed', 'mod_syllabus');
    Notification.alert(title, error.message);
};

/**
 * Reads an `<input type="datetime-local">` element's value as a Unix timestamp, or null when
 * empty. Only the week's synchronous meeting date/time still uses this raw input type — start/
 * end dates use the day/month/year date_select component instead (readDateSelectTimestamp).
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
 * Converts every `[data-timestamp]` date input's server-provided Unix timestamp into the
 * ISO-ish string the `datetime-local` input type expects as its value — mustache only has the
 * raw timestamp to hand it, so this runs once at init time against the whole page.
 *
 * @param {HTMLElement} container
 */
const hydrateDateInputs = (container) => {
    container.querySelectorAll('input[data-timestamp]').forEach((input) => {
        const timestamp = parseInt(input.dataset.timestamp, 10);
        if (!timestamp) {
            return;
        }
        const date = new Date(timestamp * 1000);
        input.value = date.toISOString().slice(0, 16);
    });
};

/**
 * Shows the "saving..." status next to a row.
 *
 * @param {?HTMLElement} status The row's own .syllabus-save-status element.
 */
const showRowSaving = (status) => {
    if (status) {
        status.classList.remove('d-none');
        status.textContent = status.dataset.saving;
    }
};

/**
 * Shows the "saved" status next to a row, fading it out shortly after.
 *
 * @param {?HTMLElement} status The row's own .syllabus-save-status element.
 */
const showRowSaved = (status) => {
    if (status) {
        status.textContent = status.dataset.saved;
        setTimeout(() => status.classList.add('d-none'), 3000);
    }
};

/**
 * Scrolls to and focuses the title field of a section, respecting prefers-reduced-motion —
 * used to land on a freshly created week/activity right after the reload that follows its
 * creation.
 *
 * @param {HTMLElement} section
 * @param {string} titleSelector
 */
const scrollAndFocus = (section, titleSelector) => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    section.scrollIntoView({behavior: reduceMotion ? 'auto' : 'smooth', block: 'start'});
    const titleInput = section.querySelector(titleSelector);
    if (titleInput) {
        titleInput.focus();
    }
};

/**
 * Consumes the sessionStorage flag (if any) left by a just-completed week/activity creation,
 * scrolling to and focusing the resulting row. Called once at init(), after the reload that
 * follows creation.
 */
const focusJustCreatedRow = () => {
    const weekId = sessionStorage.getItem(FOCUS_WEEK_KEY);
    if (weekId) {
        sessionStorage.removeItem(FOCUS_WEEK_KEY);
        const section = document.getElementById(`syllabus-section-week-${weekId}`);
        if (section) {
            scrollAndFocus(section, '.syllabus-week-title');
        }
        return;
    }

    const activityId = sessionStorage.getItem(FOCUS_ACTIVITY_KEY);
    if (activityId) {
        sessionStorage.removeItem(FOCUS_ACTIVITY_KEY);
        const row = document.querySelector(`.syllabus-activity-row[data-activityid="${activityId}"]`);
        if (row) {
            scrollAndFocus(row, '.syllabus-activity-title');
        }
    }
};

/**
 * Calls mod_syllabus_save_week, showing a plain alert on rejection.
 *
 * @param {Object} args Web service arguments, minus cmid.
 * @returns {Promise<?Object>} The web service result, or null on rejection.
 */
const persistWeek = async(args) => {
    try {
        return await Ajax.call([{methodname: 'mod_syllabus_save_week', args: {cmid, ...args}}])[0];
    } catch (error) {
        showRejected(error);
        return null;
    }
};

/**
 * Calls mod_syllabus_save_activity, showing a plain alert on rejection.
 *
 * @param {Object} args Web service arguments, minus cmid.
 * @returns {Promise<?Object>} The web service result, or null on rejection.
 */
const persistActivity = async(args) => {
    try {
        return await Ajax.call([{methodname: 'mod_syllabus_save_activity', args: {cmid, ...args}}])[0];
    } catch (error) {
        showRejected(error);
        return null;
    }
};

/**
 * Autosaves an existing week row's current structural field values.
 *
 * @param {HTMLElement} row The .syllabus-week-row element.
 */
const autosaveWeek = async(row) => {
    const weekid = parseInt(row.dataset.weekid, 10);
    if (!weekid) {
        return;
    }
    const title = row.querySelector('.syllabus-week-title').value.trim();
    if (!title) {
        row.querySelector('.syllabus-week-title').focus();
        return;
    }
    const durationInput = row.querySelector('.syllabus-week-duration').value;
    const duration = durationInput === '' ? null : parseInt(durationInput, 10);
    const startdate = readDateSelectTimestamp(row.querySelector('.syllabus-week-startdate'));
    const enddate = readDateSelectTimestamp(row.querySelector('.syllabus-week-enddate'));
    const syncdate = readTimestamp(row.querySelector('.syllabus-week-syncdate'));
    const synclink = row.querySelector('.syllabus-week-synclink').value.trim() || null;
    const synctopic = row.querySelector('.syllabus-week-synctopic').value.trim() || null;

    const status = row.querySelector('.syllabus-week-save-status');
    showRowSaving(status);
    const result = await persistWeek({weekid, title, duration, startdate, enddate, syncdate, synclink, synctopic});
    if (result) {
        showRowSaved(status);
    }
};

/**
 * Autosaves an existing activity row's current structural field values.
 *
 * @param {HTMLElement} row The .syllabus-activity-row element.
 */
const autosaveActivity = async(row) => {
    const activityid = parseInt(row.dataset.activityid, 10);
    if (!activityid) {
        return;
    }
    const weekid = parseInt(row.dataset.weekid, 10);
    const title = row.querySelector('.syllabus-activity-title').value.trim();
    if (!title) {
        row.querySelector('.syllabus-activity-title').focus();
        return;
    }
    const type = row.querySelector('.syllabus-activity-type').value.trim() || null;
    const category = row.querySelector('.syllabus-activity-category').value.trim() || null;
    const pointsInput = row.querySelector('.syllabus-activity-points').value;
    const points = pointsInput === '' ? null : parseFloat(pointsInput);
    const startdate = readDateSelectTimestamp(row.querySelector('.syllabus-activity-startdate'));
    const enddate = readDateSelectTimestamp(row.querySelector('.syllabus-activity-enddate'));
    const isfinalassessmentInput = row.querySelector('.syllabus-activity-isfinalassessment');
    const isfinalassessment = isfinalassessmentInput ? isfinalassessmentInput.checked : false;

    const status = row.querySelector('.syllabus-activity-save-status');
    showRowSaving(status);
    const result = await persistActivity({
        weekid, activityid, title, type, category, startdate, enddate, points, isfinalassessment,
    });
    if (result) {
        showRowSaved(status);
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
        showRejected(error);
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
        showRejected(error);
    }
};

/**
 * Creates a new week with a default title ("Week N") and reloads, landing on its fieldset —
 * fully open, with dates, synchronous meeting, narrative fields and its own activities area —
 * instead of the old two-step "reveal a blank row, then save it" flow.
 *
 * @param {HTMLElement} container The .path-mod-syllabus element.
 */
const createWeek = async(container) => {
    const count = container.querySelectorAll('.syllabus-week-row').length;
    const title = await getString('defaultweektitle', 'mod_syllabus', count + 1);
    const result = await persistWeek({
        weekid: 0, title, duration: null, startdate: null, enddate: null,
        syncdate: null, synclink: null, synctopic: null,
    });
    if (result) {
        sessionStorage.setItem(FOCUS_WEEK_KEY, String(result.weekid));
        reload();
    }
};

/**
 * Creates a new activity with a default title ("Activity N") within a given week and reloads,
 * landing on its row fully open with narrative fields ready — same reasoning as createWeek().
 *
 * @param {HTMLElement} weekRow The .syllabus-week-row element to add the activity to.
 */
const createActivity = async(weekRow) => {
    const weekid = parseInt(weekRow.dataset.weekid, 10);
    const count = weekRow.querySelectorAll('.syllabus-activity-row').length;
    const title = await getString('defaultactivitytitle', 'mod_syllabus', count + 1);
    const result = await persistActivity({
        weekid, activityid: 0, title, type: null, category: null,
        startdate: null, enddate: null, points: null, isfinalassessment: false,
    });
    if (result) {
        sessionStorage.setItem(FOCUS_ACTIVITY_KEY, String(result.activityid));
        reload();
    }
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

    hydrateDateInputs(container);
    focusJustCreatedRow();

    const addWeekBtn = container.querySelector('.syllabus-add-week');
    if (addWeekBtn) {
        addWeekBtn.addEventListener('click', () => createWeek(container));

        // A plan with zero weeks starts by creating the first one automatically — same
        // "Add week" call the button itself makes — instead of landing on a page with
        // nothing but that lone button. "Add week" stays visible below for a second week.
        if (!container.querySelector('.syllabus-week-row')) {
            createWeek(container);
        }
    }

    // Delegated change handling autosaves an existing week/activity row's structural fields.
    // Narrative Custom Field boxes (inside .syllabus-field) have their own autosave.js
    // listener on 'input', not 'change', and are explicitly skipped here to avoid a redundant
    // resave firing alongside it.
    container.addEventListener('change', (e) => {
        if (e.target.closest('.syllabus-field')) {
            return;
        }
        const activityRow = e.target.closest('.syllabus-activity-row');
        if (activityRow) {
            autosaveActivity(activityRow);
            return;
        }
        const weekRow = e.target.closest('.syllabus-week-row');
        if (weekRow) {
            autosaveWeek(weekRow);
        }
    });

    // Delegated click handling for delete/add-activity buttons.
    container.addEventListener('click', (e) => {
        const deleteWeekBtn = e.target.closest('.syllabus-delete-week');
        if (deleteWeekBtn) {
            deleteWeek(deleteWeekBtn.closest('.syllabus-week-row'));
            return;
        }

        const deleteActivityBtn = e.target.closest('.syllabus-delete-activity');
        if (deleteActivityBtn) {
            deleteActivity(deleteActivityBtn.closest('.syllabus-activity-row'));
            return;
        }

        const addActivityBtn = e.target.closest('.syllabus-add-activity');
        if (addActivityBtn) {
            createActivity(addActivityBtn.closest('.syllabus-week-row'));
        }
    });
};
