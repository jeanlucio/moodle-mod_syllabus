<?php
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

namespace mod_syllabus;

use core\event\course_module_updated;
use core\task\manager;
use mod_syllabus\event\plan_approved;
use mod_syllabus\event\plan_changes_requested;
use mod_syllabus\event\plan_submitted;
use mod_syllabus\local\plan_state_manager;
use mod_syllabus\task\send_workflow_notification;

/**
 * Event observers for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Re-asserts the course module visibility mandated by the plan's own status.
     *
     * mod_form.php freezes the "Availability" field, but that only protects the activity
     * settings form — the course page's own show/hide/stealth controls (and bulk edit mode)
     * call set_coursemodule_visible() directly through core_courseformat\local\stateactions,
     * bypassing the form entirely. This observer is the second line of defence: whenever this
     * course module changes, if its visibility no longer matches what the plan's status
     * mandates, it is corrected back immediately. A no-op in the common case (nothing to
     * correct), so it adds no meaningful overhead to ordinary edits.
     *
     * @param course_module_updated $event The triggered event.
     * @return void
     */
    public static function course_module_updated(course_module_updated $event): void {
        global $DB;

        if ($event->other['modulename'] !== 'syllabus') {
            return;
        }

        $status = $DB->get_field('syllabus', 'status', ['id' => $event->other['instanceid']]);
        if ($status === false) {
            return;
        }

        $expectedvisible = (int) ($status === plan_state_manager::STATUS_APPROVED);

        $cm = $DB->get_record(
            'course_modules',
            ['id' => $event->objectid],
            'id, visible, visibleoncoursepage',
            IGNORE_MISSING
        );
        if (!$cm) {
            return;
        }
        if ((int) $cm->visible === $expectedvisible && (int) $cm->visibleoncoursepage === $expectedvisible) {
            return;
        }

        set_coursemodule_visible((int) $cm->id, $expectedvisible, $expectedvisible);
    }

    /**
     * Queues a background task to notify every user with mod/syllabus:review in the context
     * that a plan awaits review.
     *
     * Notifying is queued rather than sent inline because message_send() dispatches
     * synchronously to every enabled message processor, including e-mail — on a site with a
     * real SMTP relay this blocks the submitting AJAX request for a second or more per
     * recipient. See send_workflow_notification for the actual sending logic.
     *
     * @param plan_submitted $event The triggered event.
     * @return void
     */
    public static function plan_submitted(plan_submitted $event): void {
        $task = new send_workflow_notification();
        $task->set_custom_data([
            'type'          => 'submitted',
            'planid'        => $event->objectid,
            'triggeruserid' => $event->userid,
        ]);
        manager::queue_adhoc_task($task);
    }

    /**
     * Queues a background task to notify the plan's author that their submission was approved.
     *
     * @param plan_approved $event The triggered event.
     * @return void
     */
    public static function plan_approved(plan_approved $event): void {
        $task = new send_workflow_notification();
        $task->set_custom_data([
            'type'   => 'approved',
            'planid' => $event->objectid,
        ]);
        manager::queue_adhoc_task($task);
    }

    /**
     * Queues a background task to notify the plan's author that the coordinator requested
     * changes.
     *
     * @param plan_changes_requested $event The triggered event.
     * @return void
     */
    public static function plan_changes_requested(plan_changes_requested $event): void {
        $task = new send_workflow_notification();
        $task->set_custom_data([
            'type'   => 'changes_requested',
            'planid' => $event->objectid,
            'reason' => $event->other['reason'],
        ]);
        manager::queue_adhoc_task($task);
    }
}
