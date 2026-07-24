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
 * Unit tests for the mod_syllabus_save_activity external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\save_activity
 */
final class save_activity_test extends advanced_testcase {
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
     * Creates a course with a syllabus instance, a week, and an enrolled editing teacher.
     *
     * @return array [\stdClass $syllabus, array $week, \stdClass $teacher]
     */
    private function create_syllabus_with_week(): array {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $week = save_week::execute($syllabus->cmid, 0, 'Week 1', null, null, null, null, null, null, 1);

        return [$syllabus, $week, $teacher];
    }

    /**
     * A new activity can be created under a week.
     *
     * @return void
     */
    public function test_creates_new_activity(): void {
        global $DB;

        [$syllabus, $week] = $this->create_syllabus_with_week();

        $result = save_activity::execute(
            $syllabus->cmid,
            $week['weekid'],
            0,
            'Forum discussion',
            'forum',
            'asynchronous',
            null,
            null,
            10.5
        );
        $result = external_api::clean_returnvalue(save_activity::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $activity = $DB->get_record('syllabus_activities', ['id' => $result['activityid']], '*', MUST_EXIST);
        $this->assertEquals($week['weekid'], $activity->weekid);
        $this->assertSame('Forum discussion', $activity->title);
        $this->assertEquals(10.5, $activity->points);
    }

    /**
     * An activity cannot be saved against a week that belongs to a different syllabus instance.
     *
     * @return void
     */
    public function test_cross_instance_week_rejected(): void {
        [$syllabusa, , $teachera] = $this->create_syllabus_with_week();
        [, $weekb] = $this->create_syllabus_with_week();

        $this->setUser($teachera);
        $this->expectException(\dml_missing_record_exception::class);
        save_activity::execute($syllabusa->cmid, $weekb['weekid'], 0, 'Hijacked', null, null, null, null, null);
    }

    /**
     * Resubmitting identical values on an approved plan does not spuriously reopen it.
     *
     * @return void
     */
    public function test_unchanged_values_do_not_reopen_approved_plan(): void {
        global $DB;

        [$syllabus, $week] = $this->create_syllabus_with_week();
        $created = save_activity::execute(
            $syllabus->cmid,
            $week['weekid'],
            0,
            'Forum discussion',
            null,
            null,
            null,
            null,
            null
        );

        $DB->set_field('syllabus', 'status', plan_state_manager::STATUS_APPROVED, ['id' => $syllabus->id]);

        save_activity::execute(
            $syllabus->cmid,
            $week['weekid'],
            $created['activityid'],
            'Forum discussion',
            null,
            null,
            null,
            null,
            null
        );

        $status = $DB->get_field('syllabus', 'status', ['id' => $syllabus->id]);
        $this->assertSame(plan_state_manager::STATUS_APPROVED, $status);
    }
}
