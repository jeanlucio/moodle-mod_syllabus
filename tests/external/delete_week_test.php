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
 * Unit tests for the mod_syllabus_delete_week external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\delete_week
 */
final class delete_week_test extends advanced_testcase {
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
     * Deleting a week also deletes its activities.
     *
     * @return void
     */
    public function test_deletes_week_and_its_activities(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $week = save_week::execute($syllabus->cmid, 0, 'Week 1', null, null, null);
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

        $result = delete_week::execute($syllabus->cmid, $week['weekid']);
        $result = external_api::clean_returnvalue(delete_week::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('syllabus_weeks', ['id' => $week['weekid']]));
        $this->assertFalse($DB->record_exists('syllabus_activities', ['id' => $activity['activityid']]));
    }

    /**
     * Deleting a week on an approved plan reopens it for review.
     *
     * @return void
     */
    public function test_reopens_approved_plan(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $week = save_week::execute($syllabus->cmid, 0, 'Week 1', null, null, null);
        $DB->set_field('syllabus', 'status', plan_state_manager::STATUS_APPROVED, ['id' => $syllabus->id]);

        delete_week::execute($syllabus->cmid, $week['weekid']);

        $status = $DB->get_field('syllabus', 'status', ['id' => $syllabus->id]);
        $this->assertSame(plan_state_manager::STATUS_SUBMITTED, $status);
    }
}
