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
 * Unit tests for the mod_syllabus_save_final_assessment external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\save_final_assessment
 */
final class save_final_assessment_test extends advanced_testcase {
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
     * The Final assessment fields are saved onto the syllabus row.
     *
     * @return void
     */
    public function test_saves_final_assessment(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_syllabus_with_teacher();
        $this->setUser($teacher);

        $result = save_final_assessment::execute(
            $syllabus->cmid,
            'Final exam',
            'quiz',
            1749000000,
            1749600000,
            100.0
        );
        $result = external_api::clean_returnvalue(save_final_assessment::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $updated = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertSame('Final exam', $updated->finalassessmenttitle);
        $this->assertSame('quiz', $updated->finalassessmenttype);
        $this->assertEquals(1749000000, $updated->finalassessmentstartdate);
        $this->assertEquals(1749600000, $updated->finalassessmentenddate);
        $this->assertEquals(100.0, $updated->finalassessmentpoints);
    }

    /**
     * A change to these fields is schedule-defining structure, unlike Characterisation — it
     * reopens an approved plan for review, same treatment save_activity gives a regular
     * activity's structural fields.
     *
     * @return void
     */
    public function test_reopens_approved_plan_on_change(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_syllabus_with_teacher(plan_state_manager::STATUS_APPROVED);
        $this->setUser($teacher);

        save_final_assessment::execute($syllabus->cmid, 'Final exam', 'quiz', null, null, 100.0);

        $status = $DB->get_field('syllabus', 'status', ['id' => $syllabus->id]);
        $this->assertSame(plan_state_manager::STATUS_SUBMITTED, $status);
    }

    /**
     * Resubmitting identical values on an approved plan does not spuriously reopen it.
     *
     * @return void
     */
    public function test_unchanged_values_do_not_reopen_approved_plan(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_syllabus_with_teacher();
        $this->setUser($teacher);
        save_final_assessment::execute($syllabus->cmid, 'Final exam', 'quiz', null, null, 100.0);

        $DB->set_field('syllabus', 'status', plan_state_manager::STATUS_APPROVED, ['id' => $syllabus->id]);
        save_final_assessment::execute($syllabus->cmid, 'Final exam', 'quiz', null, null, 100.0);

        $status = $DB->get_field('syllabus', 'status', ['id' => $syllabus->id]);
        $this->assertSame(plan_state_manager::STATUS_APPROVED, $status);
    }

    /**
     * A user without mod/syllabus:submit cannot save the Final assessment.
     *
     * @return void
     */
    public function test_requires_capability(): void {
        global $DB;

        [$syllabus] = $this->create_syllabus_with_teacher();
        $DB->set_field('course_modules', 'visible', 1, ['id' => $syllabus->cmid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $syllabus->course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        save_final_assessment::execute($syllabus->cmid, 'Final exam', 'quiz', null, null, 100.0);
    }
}
