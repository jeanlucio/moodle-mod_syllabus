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
use mod_syllabus\local\plan_state_manager;
use mod_syllabus\task\send_workflow_notification;

/**
 * Unit tests for the mod_syllabus_submit_plan external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\submit_plan
 */
final class submit_plan_test extends advanced_testcase {
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
     * A draft plan can be submitted by its author, queuing the reviewer notification task.
     *
     * The task's own content/recipients (which reviewers get notified, message text) are
     * send_workflow_notification_test.php's responsibility — sending is queued rather than done
     * inline precisely so this external function call returns immediately (see
     * send_workflow_notification's own docblock).
     *
     * @return void
     */
    public function test_submits_and_queues_reviewer_notification(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');

        $this->setUser($teacher);
        $result = submit_plan::execute($syllabus->cmid);
        $result = external_api::clean_returnvalue(submit_plan::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertSame(plan_state_manager::STATUS_SUBMITTED, $result['status']);
        $this->assertSame(
            plan_state_manager::STATUS_SUBMITTED,
            $DB->get_field('syllabus', 'status', ['id' => $syllabus->id])
        );

        $tasks = manager::get_adhoc_tasks(send_workflow_notification::class);
        $this->assertCount(1, $tasks);
        $data = reset($tasks)->get_custom_data();
        $this->assertSame('submitted', $data->type);
        $this->assertEquals($syllabus->id, $data->planid);
        $this->assertEquals($teacher->id, $data->triggeruserid);
    }

    /**
     * A resubmission after changes were requested can carry an optional note to the
     * coordinator, stored on the plan itself (see plan_state_manager::submit()).
     *
     * @return void
     */
    public function test_resubmit_stores_the_optional_note(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $reviewer = $this->getDataGenerator()->create_user();

        $this->setUser($teacher);
        submit_plan::execute($syllabus->cmid);
        $this->setUser($reviewer);
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Fix the bibliography.');

        $this->setUser($teacher);
        submit_plan::execute($syllabus->cmid, 'Fixed the bibliography as requested.');

        $this->assertSame(
            'Fixed the bibliography as requested.',
            $DB->get_field('syllabus', 'resubmissionnote', ['id' => $syllabus->id])
        );
    }

    /**
     * A user without mod/syllabus:submit cannot submit a plan.
     *
     * @return void
     */
    public function test_requires_capability(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $DB->set_field('course_modules', 'visible', 1, ['id' => $syllabus->cmid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        submit_plan::execute($syllabus->cmid);
    }
}
