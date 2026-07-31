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

namespace mod_syllabus\task;

use context_module;
use core\message\message;
use core\task\adhoc_task;
use core_user;
use moodle_url;
use stdClass;

/**
 * Sends the workflow notification message(s) for a syllabus plan transition, in the background.
 *
 * Queued by mod_syllabus\observer's plan_submitted/plan_approved/plan_changes_requested event
 * handlers instead of calling message_send() inline. message_send() dispatches synchronously to
 * every enabled message processor, including e-mail — on a site with a real SMTP relay
 * configured, a single call measured ~1.6s, blocking the submit/approve/request-changes AJAX
 * request for several seconds when there are multiple reviewers to notify. Running it as an
 * adhoc task lets the workflow action itself return immediately; the actual sending happens on
 * the next cron run instead.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class send_workflow_notification extends adhoc_task {
    #[\Override]
    public function execute(): void {
        $data = $this->get_custom_data();

        switch ($data->type) {
            case 'submitted':
                $this->notify_submitted((int) $data->planid, (int) $data->triggeruserid);
                break;
            case 'approved':
                $this->notify_approved((int) $data->planid);
                break;
            case 'changes_requested':
                $this->notify_changes_requested((int) $data->planid, (string) $data->reason);
                break;
        }
    }

    /**
     * Notifies every user with mod/syllabus:review in the context that a plan awaits review.
     *
     * @param int $planid Syllabus record id.
     * @param int $submitterid The user who submitted, excluded from the recipient list even if
     *     they also hold the review capability.
     * @return void
     */
    private function notify_submitted(int $planid, int $submitterid): void {
        [$plan, $cm] = $this->load_plan($planid);
        if ($plan === null) {
            return;
        }

        $context = context_module::instance($cm->id);
        $subject = get_string('messagesubjectsubmitted', 'mod_syllabus', format_string($plan->name));
        $body = $this->build_submitted_body($plan, $cm);

        foreach (get_users_by_capability($context, 'mod/syllabus:review') as $recipient) {
            if ((int) $recipient->id === $submitterid) {
                continue;
            }
            $this->send_message($plan, $cm, $recipient, 'plan_submitted', $subject, $body);
        }
    }

    /**
     * Notifies the plan's author that their submission was approved.
     *
     * @param int $planid Syllabus record id.
     * @return void
     */
    private function notify_approved(int $planid): void {
        [$plan, $cm] = $this->load_plan($planid);
        $author = $plan && $plan->submittedby ? core_user::get_user($plan->submittedby) : null;
        if ($plan === null || $author === null) {
            return;
        }

        $this->send_message(
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
     * @param int $planid Syllabus record id.
     * @param string $reason Coordinator's justification text.
     * @return void
     */
    private function notify_changes_requested(int $planid, string $reason): void {
        [$plan, $cm] = $this->load_plan($planid);
        $author = $plan && $plan->submittedby ? core_user::get_user($plan->submittedby) : null;
        if ($plan === null || $author === null) {
            return;
        }

        $a = (object) ['name' => format_string($plan->name), 'reason' => $reason];
        $this->send_message(
            $plan,
            $cm,
            $author,
            'plan_changes_requested',
            get_string('messagesubjectchangesrequested', 'mod_syllabus', format_string($plan->name)),
            get_string('messagebodychangesrequested', 'mod_syllabus', $a)
        );
    }

    /**
     * Fetches the plan and its course module fresh — time may have passed since the event that
     * queued this task, and the plan or its course module may since have been deleted.
     *
     * @param int $planid Syllabus record id.
     * @return array Two elements: the syllabus record and its course_modules record, or
     *     [null, null] when either no longer exists.
     */
    private function load_plan(int $planid): array {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $planid], '*', IGNORE_MISSING);
        $cm = $plan ? get_coursemodule_from_instance('syllabus', $plan->id, $plan->course, false, IGNORE_MISSING) : null;
        if (!$plan || !$cm) {
            return [null, null];
        }
        return [$plan, $cm];
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
    private function build_submitted_body(stdClass $plan, stdClass $cm): string {
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
    private function send_message(
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
