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

namespace mod_syllabus;

use advanced_testcase;
use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;
use mod_syllabus\external\save_activity;
use mod_syllabus\external\save_customfield_value;
use mod_syllabus\external\save_week;

/**
 * Unit tests for mod_syllabus's lib.php functions.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends advanced_testcase {
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
     * A new plan always starts hidden, even if "Show on course page" was chosen on the
     * creation form — only plan_state_manager::approve() is allowed to reveal it.
     *
     * @covers ::syllabus_add_instance
     * @return void
     */
    public function test_add_instance_forces_course_module_hidden(): void {
        $course = $this->getDataGenerator()->create_course();

        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id], ['visible' => 1]);

        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $this->assertEquals(0, $cm->visible);
    }

    /**
     * Editing the standard mod_form fields (name/intro) persists them and bumps
     * timemodified, without touching any workflow field.
     *
     * @covers ::syllabus_update_instance
     * @return void
     */
    public function test_update_instance_persists_name_and_leaves_workflow_untouched(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);

        $data = clone $syllabus;
        $data->instance = $syllabus->id;
        $data->name = 'Updated plan name';
        $data->timemodified = 0;

        $this->assertTrue(syllabus_update_instance($data));

        $updated = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertSame('Updated plan name', $updated->name);
        $this->assertGreaterThan(0, $updated->timemodified);
        $this->assertSame(\mod_syllabus\local\plan_state_manager::STATUS_DRAFT, $updated->status);
    }

    /**
     * Deleting an instance cascades to its weeks, its activities and every Custom Field
     * value stored against the plan, its weeks and its activities (see the Instance / course
     * deletion cleanup checklist).
     *
     * @covers ::syllabus_delete_instance
     * @return void
     */
    public function test_delete_instance_removes_weeks_activities_and_custom_field_values(): void {
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
            'Forum discussion',
            'forum',
            'asynchronous',
            null,
            null,
            10
        );

        $planhandler = plan_handler::create();
        $weekhandler = week_handler::create();
        $activityhandler = activity_handler::create();

        save_customfield_value::execute(
            $syllabus->cmid,
            'plan',
            $syllabus->id,
            $this->field_id($planhandler, 'coursedescription'),
            '<p>Plan level content</p>',
            0
        );
        save_customfield_value::execute(
            $syllabus->cmid,
            'week',
            $week['weekid'],
            $this->field_id($weekhandler, 'details'),
            '<p>Week level content</p>',
            0
        );
        save_customfield_value::execute(
            $syllabus->cmid,
            'activity',
            $activity['activityid'],
            $this->field_id($activityhandler, 'studentinstructions'),
            '<p>Activity level content</p>',
            0
        );

        $this->assertTrue(syllabus_delete_instance($syllabus->id));

        $this->assertFalse($DB->record_exists('syllabus', ['id' => $syllabus->id]));
        $this->assertFalse($DB->record_exists('syllabus_weeks', ['id' => $week['weekid']]));
        $this->assertFalse($DB->record_exists('syllabus_activities', ['id' => $activity['activityid']]));

        $this->assertEquals(0, $DB->count_records(
            'customfield_data',
            ['component' => 'mod_syllabus', 'area' => 'plan', 'instanceid' => $syllabus->id]
        ));
        $this->assertEquals(0, $DB->count_records(
            'customfield_data',
            ['component' => 'mod_syllabus', 'area' => 'week', 'instanceid' => $week['weekid']]
        ));
        $this->assertEquals(0, $DB->count_records(
            'customfield_data',
            ['component' => 'mod_syllabus', 'area' => 'activity', 'instanceid' => $activity['activityid']]
        ));
    }

    /**
     * Deleting an unknown/already-deleted instance id is a graceful no-op, never an exception.
     *
     * @covers ::syllabus_delete_instance
     * @return void
     */
    public function test_delete_instance_returns_false_for_unknown_id(): void {
        $this->assertFalse(syllabus_delete_instance(0));
    }

    /**
     * Finds a seeded field's id by its shortname within a handler.
     *
     * @param \core_customfield\handler $handler Handler to search.
     * @param string $shortname Field shortname.
     * @return int
     */
    private function field_id(\core_customfield\handler $handler, string $shortname): int {
        foreach ($handler->get_fields() as $field) {
            if ($field->get('shortname') === $shortname) {
                return (int) $field->get('id');
            }
        }
        $this->fail("Seeded field '{$shortname}' not found.");
    }
}
