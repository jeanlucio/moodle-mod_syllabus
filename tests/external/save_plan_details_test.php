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
 * Unit tests for the mod_syllabus_save_plan_details external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\save_plan_details
 */
final class save_plan_details_test extends advanced_testcase {
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
     * Creates a course with a syllabus instance and an enrolled editing teacher.
     *
     * @param string $status Initial status of the plan.
     * @return array [\stdClass $syllabus, \stdClass $teacher]
     */
    private function create_syllabus_with_teacher(string $status = plan_state_manager::STATUS_DRAFT): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        if ($status !== plan_state_manager::STATUS_DRAFT) {
            $DB->set_field('syllabus', 'status', $status, ['id' => $syllabus->id]);
        }

        return [$syllabus, $teacher];
    }

    /**
     * The Characterisation and Presentation fields are saved onto the syllabus row.
     *
     * @return void
     */
    public function test_saves_plan_details(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_syllabus_with_teacher();
        $this->setUser($teacher);

        $result = save_plan_details::execute(
            $syllabus->cmid,
            '2026.1',
            1749000000,
            1751500000,
            30,
            'https://youtu.be/8MZLYHxOdUo'
        );
        $result = external_api::clean_returnvalue(save_plan_details::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $updated = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertSame('2026.1', $updated->academicperiod);
        $this->assertEquals(1749000000, $updated->coursestartdate);
        $this->assertEquals(1751500000, $updated->courseenddate);
        $this->assertEquals(30, $updated->totalduration);
        $this->assertSame('https://youtu.be/8MZLYHxOdUo', $updated->presentationvideourl);
    }

    /**
     * These fields stay editable while the plan is awaiting review, unlike week/activity
     * structural fields — they are plan-level metadata, not schedule-defining structure
     * (see the class docblock on save_plan_details).
     *
     * @return void
     */
    public function test_editable_while_submitted(): void {
        [$syllabus, $teacher] = $this->create_syllabus_with_teacher(plan_state_manager::STATUS_SUBMITTED);
        $this->setUser($teacher);

        $result = save_plan_details::execute($syllabus->cmid, '2026.1', null, null, null, null);
        $result = external_api::clean_returnvalue(save_plan_details::execute_returns(), $result);

        $this->assertTrue($result['success']);
    }

    /**
     * Saving these fields never reopens an approved plan for review — same reasoning as
     * test_editable_while_submitted.
     *
     * @return void
     */
    public function test_does_not_reopen_approved_plan(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_syllabus_with_teacher(plan_state_manager::STATUS_APPROVED);
        $this->setUser($teacher);

        save_plan_details::execute($syllabus->cmid, '2026.2', null, null, null, null);

        $status = $DB->get_field('syllabus', 'status', ['id' => $syllabus->id]);
        $this->assertSame(plan_state_manager::STATUS_APPROVED, $status);
    }

    /**
     * A user without mod/syllabus:submit cannot save plan details.
     *
     * @return void
     */
    public function test_requires_capability(): void {
        global $DB;

        [$syllabus] = $this->create_syllabus_with_teacher();
        // Force visible so the capability check itself is what's under test, not the
        // separate (and equally intentional) gate that blocks access to a hidden activity.
        $DB->set_field('course_modules', 'visible', 1, ['id' => $syllabus->cmid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $syllabus->course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        save_plan_details::execute($syllabus->cmid, '2026.1', null, null, null, null);
    }
}
