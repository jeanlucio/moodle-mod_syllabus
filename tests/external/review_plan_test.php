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

namespace mod_syllabus\external;

use advanced_testcase;
use core\task\manager;
use core_external\external_api;
use invalid_parameter_exception;
use mod_syllabus\local\plan_state_manager;
use mod_syllabus\task\send_workflow_notification;
use moodle_exception;

/**
 * Unit tests for the mod_syllabus_review_plan external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\review_plan
 */
final class review_plan_test extends advanced_testcase {
    /**
     * Resets the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a course with a syllabus instance already submitted for review.
     *
     * @return array [\stdClass $syllabus, \stdClass $teacher, \stdClass $manager]
     */
    private function create_submitted_plan(): array {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');

        $this->setUser($teacher);
        submit_plan::execute($syllabus->cmid);

        return [$syllabus, $teacher, $manager];
    }

    /**
     * Fetches the single queued send_workflow_notification task of the given type — used
     * instead of a bare get_adhoc_tasks() count, since create_submitted_plan() already queues
     * its own 'submitted' task before the test's own action queues a second one.
     *
     * @param string $type
     * @return \core\task\adhoc_task
     */
    private function get_queued_task(string $type): \core\task\adhoc_task {
        $matching = array_values(array_filter(
            manager::get_adhoc_tasks(send_workflow_notification::class),
            fn ($task) => $task->get_custom_data()->type === $type
        ));
        $this->assertCount(1, $matching, "Expected exactly one queued task of type '{$type}'.");
        return $matching[0];
    }

    /**
     * A coordinator can approve a submitted plan, queuing the author notification task.
     *
     * The task's own content/recipient are send_workflow_notification_test.php's
     * responsibility — sending is queued rather than done inline precisely so this external
     * function call returns immediately (see send_workflow_notification's own docblock).
     *
     * @return void
     */
    public function test_approves_and_queues_author_notification(): void {
        global $DB;

        [$syllabus, , $manager] = $this->create_submitted_plan();

        $this->setUser($manager);
        $result = review_plan::execute($syllabus->cmid, 'approved');
        $result = external_api::clean_returnvalue(review_plan::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertSame(plan_state_manager::STATUS_APPROVED, $result['status']);
        $this->assertSame(
            plan_state_manager::STATUS_APPROVED,
            $DB->get_field('syllabus', 'status', ['id' => $syllabus->id])
        );

        $data = $this->get_queued_task('approved')->get_custom_data();
        $this->assertEquals($syllabus->id, $data->planid);
    }

    /**
     * A coordinator can request changes, queuing the author notification task with the reason.
     *
     * @return void
     */
    public function test_requests_changes_and_queues_author_notification(): void {
        global $DB;

        [$syllabus, , $manager] = $this->create_submitted_plan();

        $this->setUser($manager);
        review_plan::execute($syllabus->cmid, 'changes_requested', 'Fix the bibliography.');

        $this->assertSame(
            plan_state_manager::STATUS_CHANGES_REQUESTED,
            $DB->get_field('syllabus', 'status', ['id' => $syllabus->id])
        );

        $data = $this->get_queued_task('changes_requested')->get_custom_data();
        $this->assertEquals($syllabus->id, $data->planid);
        $this->assertSame('Fix the bibliography.', $data->reason);
    }

    /**
     * Requesting changes without a reason is rejected.
     *
     * @return void
     */
    public function test_requests_changes_requires_reason(): void {
        [$syllabus, , $manager] = $this->create_submitted_plan();
        $this->setUser($manager);

        $this->expectException(invalid_parameter_exception::class);
        review_plan::execute($syllabus->cmid, 'changes_requested', '');
    }

    /**
     * An invalid decision value is rejected.
     *
     * @return void
     */
    public function test_invalid_decision_rejected(): void {
        [$syllabus, , $manager] = $this->create_submitted_plan();
        $this->setUser($manager);

        $this->expectException(invalid_parameter_exception::class);
        review_plan::execute($syllabus->cmid, 'bogus');
    }

    /**
     * A reviewer cannot approve their own submission — the state machine's own rule surfaces
     * through the external function unchanged.
     *
     * @return void
     */
    public function test_cannot_approve_own_submission(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teachermanager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teachermanager->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teachermanager->id, $course->id, 'manager');

        $this->setUser($teachermanager);
        submit_plan::execute($syllabus->cmid);

        $this->expectException(moodle_exception::class);
        review_plan::execute($syllabus->cmid, 'approved');
    }

    /**
     * A user without mod/syllabus:review cannot review a plan.
     *
     * @return void
     */
    public function test_requires_capability(): void {
        [$syllabus, $teacher] = $this->create_submitted_plan();
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        review_plan::execute($syllabus->cmid, 'approved');
    }
}
