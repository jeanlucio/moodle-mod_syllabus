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
use core_external\external_api;
use mod_syllabus\local\plan_state_manager;

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
     * A draft plan can be submitted by its author, notifying every reviewer.
     *
     * @return void
     */
    public function test_submits_and_notifies_reviewers(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');

        $this->setUser($teacher);
        $sink = $this->redirectMessages();
        $result = submit_plan::execute($syllabus->cmid);
        $result = external_api::clean_returnvalue(submit_plan::execute_returns(), $result);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue($result['success']);
        $this->assertSame(plan_state_manager::STATUS_SUBMITTED, $result['status']);
        $this->assertSame(
            plan_state_manager::STATUS_SUBMITTED,
            $DB->get_field('syllabus', 'status', ['id' => $syllabus->id])
        );

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($manager->id, $message->useridto);
        $this->assertSame('mod_syllabus', $message->component);
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
