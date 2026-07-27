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
 * Tests for the observer's workflow notification methods.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus;

use advanced_testcase;
use context_module;
use mod_syllabus\event\plan_approved;
use mod_syllabus\event\plan_changes_requested;
use mod_syllabus\event\plan_submitted;
use mod_syllabus\local\plan_state_manager;

/**
 * observer_test.php only exercises course_module_updated (the visibility guard) — the three
 * notification methods (plan_submitted/plan_approved/plan_changes_requested) are covered here
 * instead, using the message redirect sink to assert on what was actually sent.
 *
 * @covers \mod_syllabus\observer
 */
final class observer_notifications_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Every user with mod/syllabus:review in the module context is notified, except the
     * submitter themself, even when the submitter also happens to hold that capability. The
     * body carries the submitting teacher, the course, the submission time and a direct link,
     * but never the "resubmission" note on a plan's first submission.
     *
     * @return void
     */
    public function test_plan_submitted_notifies_reviewers_but_not_the_submitter(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $submitter = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($submitter->id, $course->id, 'manager');
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');

        plan_state_manager::submit($syllabus->id, (int) $submitter->id);

        $sink = $this->redirectMessages();
        plan_submitted::create(['objectid' => $syllabus->id, 'context' => $context, 'userid' => $submitter->id])->trigger();
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
    public function test_plan_submitted_body_mentions_resubmission_after_changes_requested(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $submitter = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');

        plan_state_manager::submit($syllabus->id, (int) $submitter->id);
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Please fix the grading criteria.');
        plan_state_manager::submit($syllabus->id, (int) $submitter->id);

        $sink = $this->redirectMessages();
        plan_submitted::create(['objectid' => $syllabus->id, 'context' => $context, 'userid' => $submitter->id])->trigger();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString(
            get_string('messagedetailresubmission', 'mod_syllabus'),
            $messages[0]->fullmessage
        );
    }

    /**
     * A plan that no longer exists by the time the event is handled (deleted between trigger
     * and dispatch) is a silent no-op, never an exception.
     *
     * @return void
     */
    public function test_plan_submitted_is_a_noop_for_a_deleted_plan(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $event = plan_submitted::create(['objectid' => $syllabus->id, 'context' => $context]);

        $DB->delete_records('syllabus', ['id' => $syllabus->id]);

        $sink = $this->redirectMessages();
        observer::plan_submitted($event);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame([], $messages);
    }

    /**
     * The plan's author is notified when their submission is approved.
     *
     * @return void
     */
    public function test_plan_approved_notifies_the_author(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        $sink = $this->redirectMessages();
        plan_approved::create(['objectid' => $syllabus->id, 'context' => $context, 'userid' => $reviewer->id])->trigger();
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
    public function test_plan_changes_requested_notifies_the_author_with_the_reason(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Please fix the grading criteria.');

        $sink = $this->redirectMessages();
        plan_changes_requested::create([
            'objectid' => $syllabus->id,
            'context'  => $context,
            'userid'   => $reviewer->id,
            'other'    => ['reason' => 'Please fix the grading criteria.'],
        ])->trigger();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame((int) $author->id, (int) $messages[0]->useridto);
        $this->assertStringContainsString('Please fix the grading criteria.', $messages[0]->fullmessage);
    }
}
