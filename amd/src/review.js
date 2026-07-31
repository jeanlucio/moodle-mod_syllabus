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
 * AMD module for the approval workflow actions: submit, approve, request changes, unpublish.
 *
 * @module     mod_syllabus/review
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';

/** @type {int} Course module ID. */
let cmid = 0;

/**
 * Calls a workflow web service and reloads the page on success.
 *
 * Every rejection reaching this point is one of plan_state_manager's own moodle_exception
 * business-rule messages (wrong status, self-approval, etc.) or a capability error — both
 * already carry a clear, translated explanation. Shown via Notification.alert() (title +
 * message only) instead of Notification.exception() (which renders Moodle's generic AJAX
 * error dialog, complete with a raw error code as its title and a stack trace) — that dialog
 * is meant for genuinely unexpected failures, not an outcome the workflow itself anticipates.
 *
 * @param {string} methodname
 * @param {object} args
 */
const callAndReload = async(methodname, args) => {
    try {
        await Ajax.call([{methodname, args}])[0];
        window.location.reload();
    } catch (error) {
        const title = await getString('actionnotallowed', 'mod_syllabus');
        Notification.alert(title, error.message);
    }
};

/**
 * Initialize the workflow action buttons present on the page.
 *
 * @param {int} coursemoduleid Course module ID.
 */
export const init = (coursemoduleid) => {
    cmid = coursemoduleid;

    const container = document.querySelector('.path-mod-syllabus');
    if (!container) {
        return;
    }

    const submitBtn = container.querySelector('.syllabus-submit-plan');
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            const noteField = container.querySelector('.syllabus-resubmission-note');
            const note = noteField ? noteField.value.trim() : '';
            callAndReload('mod_syllabus_submit_plan', {cmid, note});
        });
    }

    const approveBtn = container.querySelector('.syllabus-approve-plan');
    if (approveBtn) {
        approveBtn.addEventListener('click', () => {
            callAndReload('mod_syllabus_review_plan', {cmid, decision: 'approved', reason: ''});
        });
    }

    const requestChangesBtn = container.querySelector('.syllabus-request-changes');
    if (requestChangesBtn) {
        requestChangesBtn.addEventListener('click', () => {
            const reasonField = container.querySelector('.syllabus-changes-reason');
            const reason = reasonField.value.trim();
            if (!reason) {
                reasonField.focus();
                return;
            }
            callAndReload('mod_syllabus_review_plan', {cmid, decision: 'changes_requested', reason});
        });
    }

    const unpublishBtn = container.querySelector('.syllabus-unpublish');
    if (unpublishBtn) {
        unpublishBtn.addEventListener('click', () => {
            // eslint-disable-next-line no-alert
            if (!window.confirm(unpublishBtn.dataset.confirm)) {
                return;
            }
            callAndReload('mod_syllabus_unpublish_plan', {cmid});
        });
    }
};
