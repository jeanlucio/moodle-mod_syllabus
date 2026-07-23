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
 * Unit tests for the mod_syllabus_save_week external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\save_week
 */
final class save_week_test extends advanced_testcase {
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
     * A new week can be created, becoming the first entry in sortorder.
     *
     * @return void
     */
    public function test_creates_new_week(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_syllabus_with_teacher();
        $this->setUser($teacher);

        $result = save_week::execute(
            $syllabus->cmid,
            0,
            'Week 1',
            90,
            1700000000,
            1700086400,
            1700050000,
            'https://meet.example.org/week1',
            'Kickoff session'
        );
        $result = external_api::clean_returnvalue(save_week::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $week = $DB->get_record('syllabus_weeks', ['id' => $result['weekid']], '*', MUST_EXIST);
        $this->assertEquals($syllabus->id, $week->syllabusid);
        $this->assertSame('Week 1', $week->title);
        $this->assertEquals(0, $week->sortorder);
        $this->assertEquals(1700050000, $week->syncdate);
        $this->assertSame('https://meet.example.org/week1', $week->synclink);
        $this->assertSame('Kickoff session', $week->synctopic);
    }

    /**
     * An existing week's fields can be updated.
     *
     * @return void
     */
    public function test_updates_existing_week(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_syllabus_with_teacher();
        $this->setUser($teacher);

        $created = save_week::execute($syllabus->cmid, 0, 'Original title', 60, null, null, null, null, null);
        save_week::execute($syllabus->cmid, $created['weekid'], 'Updated title', 120, null, null, null, null, null);

        $week = $DB->get_record('syllabus_weeks', ['id' => $created['weekid']], '*', MUST_EXIST);
        $this->assertSame('Updated title', $week->title);
        $this->assertEquals(120, $week->duration);
    }

    /**
     * A week belonging to a different syllabus instance cannot be edited through this cmid.
     *
     * @return void
     */
    public function test_cross_instance_week_rejected(): void {
        [$syllabusa, $teachera] = $this->create_syllabus_with_teacher();
        [$syllabusb, $teacherb] = $this->create_syllabus_with_teacher();

        $this->setUser($teacherb);
        $otherweek = save_week::execute($syllabusb->cmid, 0, 'Belongs to B', null, null, null, null, null, null);

        $this->setUser($teachera);
        $this->expectException(\dml_missing_record_exception::class);
        save_week::execute($syllabusa->cmid, $otherweek['weekid'], 'Hijacked', null, null, null, null, null, null);
    }

    /**
     * Structural edits are blocked while the plan is awaiting review.
     *
     * @return void
     */
    public function test_blocked_while_submitted(): void {
        [$syllabus, $teacher] = $this->create_syllabus_with_teacher(plan_state_manager::STATUS_SUBMITTED);
        $this->setUser($teacher);

        $this->expectException(moodle_exception::class);
        save_week::execute($syllabus->cmid, 0, 'New week', null, null, null, null, null, null);
    }

    /**
     * A structural edit on an approved plan reopens it for review.
     *
     * @return void
     */
    public function test_reopens_approved_plan(): void {
        global $DB;

        [$syllabus, $teacher] = $this->create_syllabus_with_teacher();
        $this->setUser($teacher);
        $created = save_week::execute($syllabus->cmid, 0, 'Week 1', 60, null, null, null, null, null);

        $DB->set_field('syllabus', 'status', plan_state_manager::STATUS_APPROVED, ['id' => $syllabus->id]);

        save_week::execute($syllabus->cmid, $created['weekid'], 'Week 1 revised', 60, null, null, null, null, null);

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
        $created = save_week::execute($syllabus->cmid, 0, 'Week 1', 60, null, null, null, null, null);

        $DB->set_field('syllabus', 'status', plan_state_manager::STATUS_APPROVED, ['id' => $syllabus->id]);

        save_week::execute($syllabus->cmid, $created['weekid'], 'Week 1', 60, null, null, null, null, null);

        $status = $DB->get_field('syllabus', 'status', ['id' => $syllabus->id]);
        $this->assertSame(plan_state_manager::STATUS_APPROVED, $status);
    }

    /**
     * A user without mod/syllabus:submit cannot save a week.
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
        save_week::execute($syllabus->cmid, 0, 'Week 1', null, null, null, null, null, null);
    }
}
