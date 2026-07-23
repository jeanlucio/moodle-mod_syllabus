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
use moodle_exception;

/**
 * Unit tests for the mod_syllabus_unpublish_plan external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\unpublish_plan
 */
final class unpublish_plan_test extends advanced_testcase {
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
     * Creates a course with a syllabus instance already approved.
     *
     * @return array [\stdClass $syllabus, \stdClass $teacher, \stdClass $manager]
     */
    private function create_approved_plan(): array {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');

        $this->setUser($teacher);
        submit_plan::execute($syllabus->cmid);
        $this->setUser($manager);
        review_plan::execute($syllabus->cmid, 'approved');

        return [$syllabus, $teacher, $manager];
    }

    /**
     * The plan's own author can unpublish it, even without mod/syllabus:review.
     *
     * @return void
     */
    public function test_author_can_unpublish(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_approved_plan();
        $this->setUser($teacher);

        $result = unpublish_plan::execute($syllabus->cmid);
        $result = external_api::clean_returnvalue(unpublish_plan::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertSame(plan_state_manager::STATUS_DRAFT, $result['status']);
        $this->assertEquals($teacher->id, $DB->get_field('syllabus', 'unpublishedby', ['id' => $syllabus->id]));
    }

    /**
     * A coordinator can unpublish a plan they did not author.
     *
     * @return void
     */
    public function test_reviewer_can_unpublish(): void {
        [$syllabus, , $manager] = $this->create_approved_plan();
        $this->setUser($manager);

        $result = unpublish_plan::execute($syllabus->cmid);
        $result = external_api::clean_returnvalue(unpublish_plan::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertSame(plan_state_manager::STATUS_DRAFT, $result['status']);
    }

    /**
     * An unrelated teacher — sharing mod/syllabus:submit but neither the author nor a
     * reviewer — cannot unpublish someone else's plan.
     *
     * @return void
     */
    public function test_unrelated_teacher_cannot_unpublish(): void {
        [$syllabus] = $this->create_approved_plan();

        $otherteacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($otherteacher->id, $syllabus->course, 'editingteacher');
        $this->setUser($otherteacher);

        $this->expectException(\required_capability_exception::class);
        unpublish_plan::execute($syllabus->cmid);
    }

    /**
     * A plan that is not approved cannot be unpublished.
     *
     * @return void
     */
    public function test_requires_approved_status(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        // Use a manager (mod/syllabus:review), not a plain teacher: the capability gate in
        // unpublish_plan runs before plan_state_manager's own status check, so a caller who
        // fails the capability check would hit required_capability_exception first, masking
        // the status-validity assertion this test targets.
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');
        $this->setUser($manager);

        $this->expectException(moodle_exception::class);
        unpublish_plan::execute($syllabus->cmid);
    }
}
