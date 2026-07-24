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

/**
 * Unit tests for the mod_syllabus_delete_activity external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\delete_activity
 */
final class delete_activity_test extends advanced_testcase {
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
     * An activity can be deleted from a week.
     *
     * @return void
     */
    public function test_deletes_activity(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $week = save_week::execute($syllabus->cmid, 0, 'Week 1', null, null, null, null, null, null, 1);
        $activity = save_activity::execute(
            $syllabus->cmid,
            $week['weekid'],
            0,
            'Activity 1',
            null,
            null,
            null,
            null,
            null
        );

        $result = delete_activity::execute($syllabus->cmid, $activity['activityid']);
        $result = external_api::clean_returnvalue(delete_activity::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('syllabus_activities', ['id' => $activity['activityid']]));
    }

    /**
     * An activity belonging to a different syllabus instance cannot be deleted through this cmid.
     *
     * @return void
     */
    public function test_cross_instance_activity_rejected(): void {
        $coursea = $this->getDataGenerator()->create_course();
        $syllabusa = $this->getDataGenerator()->create_module('syllabus', ['course' => $coursea->id]);
        $teachera = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teachera->id, $coursea->id, 'editingteacher');

        $courseb = $this->getDataGenerator()->create_course();
        $syllabusb = $this->getDataGenerator()->create_module('syllabus', ['course' => $courseb->id]);
        $teacherb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacherb->id, $courseb->id, 'editingteacher');

        $this->setUser($teacherb);
        $weekb = save_week::execute($syllabusb->cmid, 0, 'Week B', null, null, null, null, null, null, 1);
        $activityb = save_activity::execute(
            $syllabusb->cmid,
            $weekb['weekid'],
            0,
            'Activity B',
            null,
            null,
            null,
            null,
            null
        );

        $this->setUser($teachera);
        $this->expectException(\dml_missing_record_exception::class);
        delete_activity::execute($syllabusa->cmid, $activityb['activityid']);
    }
}
