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

use context_module;
use core\event\course_module_updated;
use core\message\message;
use core_user;
use mod_syllabus\event\plan_approved;
use mod_syllabus\event\plan_changes_requested;
use mod_syllabus\event\plan_submitted;
use mod_syllabus\local\plan_state_manager;
use moodle_url;
use stdClass;

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
     * Notifies every user with mod/syllabus:review in the context that a plan awaits review.
     *
     * @param plan_submitted $event The triggered event.
     * @return void
     */
    public static function plan_submitted(plan_submitted $event): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $event->objectid], '*', IGNORE_MISSING);
        $cm = $plan ? get_coursemodule_from_instance('syllabus', $plan->id, $plan->course, false, IGNORE_MISSING) : null;
        if (!$plan || !$cm) {
            return;
        }

        $context = context_module::instance($cm->id);
        $subject = get_string('messagesubjectsubmitted', 'mod_syllabus', format_string($plan->name));
        $body = self::build_submitted_body($plan, $cm);

        foreach (get_users_by_capability($context, 'mod/syllabus:review') as $recipient) {
            if ((int) $recipient->id === (int) $event->userid) {
                continue;
            }
            self::send_message($plan, $cm, $recipient, 'plan_submitted', $subject, $body);
        }
    }

    /**
     * Builds the "plan submitted" notification body: author, submission time, the course's own
     * expected start date (so the reviewer can judge how much turnaround time they have), and
     * whether this is a resubmission after changes were requested.
     *
     * @param stdClass $plan Syllabus record.
     * @param stdClass $cm Course module record.
     * @return string
     */
    private static function build_submitted_body(stdClass $plan, stdClass $cm): string {
        global $DB;

        $course = $DB->get_record('course', ['id' => $plan->course], 'id, fullname, startdate', IGNORE_MISSING);

        $lines = [
            get_string('messagebodysubmitted', 'mod_syllabus', (object) [
                'planname' => format_string($plan->name),
                'coursename' => $course ? format_string($course->fullname) : '',
            ]),
            '',
        ];

        if ($plan->submittedby) {
            $author = core_user::get_user($plan->submittedby);
            if ($author) {
                $lines[] = get_string('messagedetailauthor', 'mod_syllabus', fullname($author));
            }
        }
        if ($plan->timesubmitted) {
            $lines[] = get_string('messagedetailsubmitted', 'mod_syllabus', userdate($plan->timesubmitted));
        }
        if ($course) {
            $coursestart = $course->startdate
                ? userdate($course->startdate, get_string('strftimedate', 'langconfig'))
                : get_string('messagestartdatenotset', 'mod_syllabus');
            $lines[] = get_string('messagedetailcoursestart', 'mod_syllabus', $coursestart);
        }
        if ($plan->timereviewed) {
            $lines[] = get_string('messagedetailresubmission', 'mod_syllabus');
        }

        $lines[] = '';
        $lines[] = get_string(
            'messagedetaillink',
            'mod_syllabus',
            (new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]))->out(false)
        );

        return implode("\n", $lines);
    }

    /**
     * Notifies the plan's author that their submission was approved.
     *
     * @param plan_approved $event The triggered event.
     * @return void
     */
    public static function plan_approved(plan_approved $event): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $event->objectid], '*', IGNORE_MISSING);
        $cm = $plan ? get_coursemodule_from_instance('syllabus', $plan->id, $plan->course, false, IGNORE_MISSING) : null;
        $author = $plan && $plan->submittedby ? core_user::get_user($plan->submittedby) : null;
        if (!$plan || !$cm || !$author) {
            return;
        }

        self::send_message(
            $plan,
            $cm,
            $author,
            'plan_approved',
            get_string('messagesubjectapproved', 'mod_syllabus', format_string($plan->name)),
            get_string('messagebodyapproved', 'mod_syllabus', format_string($plan->name))
        );
    }

    /**
     * Notifies the plan's author that the coordinator requested changes.
     *
     * @param plan_changes_requested $event The triggered event.
     * @return void
     */
    public static function plan_changes_requested(plan_changes_requested $event): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $event->objectid], '*', IGNORE_MISSING);
        $cm = $plan ? get_coursemodule_from_instance('syllabus', $plan->id, $plan->course, false, IGNORE_MISSING) : null;
        $author = $plan && $plan->submittedby ? core_user::get_user($plan->submittedby) : null;
        if (!$plan || !$cm || !$author) {
            return;
        }

        $a = (object) ['name' => format_string($plan->name), 'reason' => $event->other['reason']];
        self::send_message(
            $plan,
            $cm,
            $author,
            'plan_changes_requested',
            get_string('messagesubjectchangesrequested', 'mod_syllabus', format_string($plan->name)),
            get_string('messagebodychangesrequested', 'mod_syllabus', $a)
        );
    }

    /**
     * Builds and sends one workflow notification.
     *
     * @param stdClass $plan Syllabus record the message is about.
     * @param stdClass $cm Course module record.
     * @param stdClass $recipient User to notify.
     * @param string $name Message provider name, as declared in db/messages.php.
     * @param string $subject Message subject.
     * @param string $body Message body (plain text).
     * @return void
     */
    private static function send_message(
        stdClass $plan,
        stdClass $cm,
        stdClass $recipient,
        string $name,
        string $subject,
        string $body
    ): void {
        $message = new message();
        $message->component = 'mod_syllabus';
        $message->name = $name;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]);
        $message->contexturlname = format_string($plan->name);
        message_send($message);
    }
}
