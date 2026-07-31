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
use core\task\manager;
use mod_syllabus\event\plan_approved;
use mod_syllabus\event\plan_changes_requested;
use mod_syllabus\event\plan_submitted;
use mod_syllabus\local\plan_state_manager;
use mod_syllabus\task\send_workflow_notification;

/**
 * observer_test.php only exercises course_module_updated (the visibility guard) — the three
 * notification methods (plan_submitted/plan_approved/plan_changes_requested) are covered here
 * instead. These only assert that the right send_workflow_notification adhoc task is queued
 * with the right custom data — the actual message content/recipients, and the deleted-plan
 * no-op case, are send_workflow_notification_test.php's responsibility, not the observer's.
 * Sending is deliberately queued rather than done inline (see send_workflow_notification's own
 * docblock): message_send() dispatches synchronously to every enabled message processor,
 * including e-mail, which can take a second or more per recipient on a site with a real SMTP
 * relay configured — blocking the submit/approve/request-changes AJAX call otherwise.
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
     * Fetches the single send_workflow_notification task queued during the current test, and
     * fails loudly if there isn't exactly one — a silent no-op here would otherwise look
     * identical to "nothing queued" further down the assertions.
     *
     * @return \core\task\adhoc_task
     */
    private function get_queued_task(): \core\task\adhoc_task {
        $tasks = manager::get_adhoc_tasks(send_workflow_notification::class);
        $this->assertCount(1, $tasks, 'Expected exactly one send_workflow_notification task to be queued.');
        return reset($tasks);
    }

    /**
     * Submitting a plan queues a task carrying the submitter's id, so the task can later
     * exclude them from the recipient list even if they also hold the review capability.
     *
     * @return void
     */
    public function test_plan_submitted_queues_a_notification_task(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $submitter = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $submitter->id);

        plan_submitted::create([
            'objectid' => $syllabus->id,
            'context'  => $context,
            'userid'   => $submitter->id,
        ])->trigger();

        $data = $this->get_queued_task()->get_custom_data();
        $this->assertSame('submitted', $data->type);
        $this->assertEquals($syllabus->id, $data->planid);
        $this->assertEquals($submitter->id, $data->triggeruserid);
    }

    /**
     * Approving a plan queues a task identifying the plan; the author is resolved later by
     * the task itself, from the plan's own submittedby column, not carried in the custom data.
     *
     * @return void
     */
    public function test_plan_approved_queues_a_notification_task(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        plan_approved::create([
            'objectid' => $syllabus->id,
            'context'  => $context,
            'userid'   => $reviewer->id,
        ])->trigger();

        $data = $this->get_queued_task()->get_custom_data();
        $this->assertSame('approved', $data->type);
        $this->assertEquals($syllabus->id, $data->planid);
    }

    /**
     * Requesting changes queues a task carrying the coordinator's reason text, since that text
     * only exists on the event itself, not anywhere the task could otherwise re-fetch it from.
     *
     * @return void
     */
    public function test_plan_changes_requested_queues_a_notification_task_with_the_reason(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Please fix the grading criteria.');

        plan_changes_requested::create([
            'objectid' => $syllabus->id,
            'context'  => $context,
            'userid'   => $reviewer->id,
            'other'    => ['reason' => 'Please fix the grading criteria.'],
        ])->trigger();

        $data = $this->get_queued_task()->get_custom_data();
        $this->assertSame('changes_requested', $data->type);
        $this->assertEquals($syllabus->id, $data->planid);
        $this->assertSame('Please fix the grading criteria.', $data->reason);
    }
}
