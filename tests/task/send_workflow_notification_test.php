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

/**
 * Tests for the send_workflow_notification adhoc task.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\task;

use advanced_testcase;
use mod_syllabus\local\plan_state_manager;

/**
 * Exercises the task directly (construct, set_custom_data(), execute()) rather than through the
 * observer/event layer — observer_notifications_test.php already covers that the observer
 * queues this task with the right custom data; this file covers what the task actually does
 * with it, using the message redirect sink to assert on what was sent.
 *
 * @covers \mod_syllabus\task\send_workflow_notification
 */
final class send_workflow_notification_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Builds and executes the task with the given custom data.
     *
     * @param array $customdata
     * @return void
     */
    private function run_task(array $customdata): void {
        $task = new send_workflow_notification();
        $task->set_custom_data($customdata);
        $task->execute();
    }

    /**
     * Every user with mod/syllabus:review in the module context is notified, except the
     * submitter themself, even when the submitter also happens to hold that capability. The
     * body carries the submitting teacher, the course, the submission time and a direct link,
     * but never the "resubmission" note on a plan's first submission.
     *
     * @return void
     */
    public function test_submitted_notifies_reviewers_but_not_the_submitter(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $submitter = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($submitter->id, $course->id, 'manager');
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');

        plan_state_manager::submit($syllabus->id, (int) $submitter->id);

        $sink = $this->redirectMessages();
        $this->run_task(['type' => 'submitted', 'planid' => $syllabus->id, 'triggeruserid' => $submitter->id]);
        $messages = $sink->get_messages();
        $sink->close();

        $recipients = array_map(fn ($m) => (int) $m->useridto, $messages);
        $this->assertContains((int) $reviewer->id, $recipients);
        $this->assertNotContains((int) $submitter->id, $recipients);
        foreach ($messages as $message) {
            $this->assertSame('mod_syllabus', $message->component);
            $this->assertSame('plan_submitted', $message->eventtype);
            $this->assertStringContainsString(fullname($submitter), $message->fullmessage);
            $this->assertStringContainsString($course->fullname, $message->fullmessage);
            $this->assertStringContainsString("id={$cm->id}", $message->fullmessage);
            $this->assertStringNotContainsString(
                get_string('messagedetailresubmission', 'mod_syllabus'),
                $message->fullmessage
            );
        }
    }

    /**
     * A submission that follows a "changes requested" round tells the reviewer it is a
     * resubmission, so they know a previous version was already reviewed once.
     *
     * @return void
     */
    public function test_submitted_body_mentions_resubmission_after_changes_requested(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $submitter = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');

        plan_state_manager::submit($syllabus->id, (int) $submitter->id);
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Please fix the grading criteria.');
        plan_state_manager::submit($syllabus->id, (int) $submitter->id);

        $sink = $this->redirectMessages();
        $this->run_task(['type' => 'submitted', 'planid' => $syllabus->id, 'triggeruserid' => $submitter->id]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString(
            get_string('messagedetailresubmission', 'mod_syllabus'),
            $messages[0]->fullmessage
        );
    }

    /**
     * A plan that no longer exists by the time the task runs (deleted between the event firing
     * and the next cron run) is a silent no-op, never an exception.
     *
     * @return void
     */
    public function test_is_a_noop_for_a_deleted_plan(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $DB->delete_records('syllabus', ['id' => $syllabus->id]);

        $sink = $this->redirectMessages();
        $this->run_task(['type' => 'submitted', 'planid' => $syllabus->id, 'triggeruserid' => 0]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame([], $messages);
    }

    /**
     * The plan's author is notified when their submission is approved.
     *
     * @return void
     */
    public function test_approved_notifies_the_author(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        $sink = $this->redirectMessages();
        $this->run_task(['type' => 'approved', 'planid' => $syllabus->id]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame((int) $author->id, (int) $messages[0]->useridto);
    }

    /**
     * The plan's author is notified when changes are requested, and the message carries the
     * coordinator's justification text.
     *
     * @return void
     */
    public function test_changes_requested_notifies_the_author_with_the_reason(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Please fix the grading criteria.');

        $sink = $this->redirectMessages();
        $this->run_task([
            'type'   => 'changes_requested',
            'planid' => $syllabus->id,
            'reason' => 'Please fix the grading criteria.',
        ]);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame((int) $author->id, (int) $messages[0]->useridto);
        $this->assertStringContainsString('Please fix the grading criteria.', $messages[0]->fullmessage);
    }
}
