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
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;
use moodle_exception;

/**
 * Unit tests for the mod_syllabus_save_customfield_value external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\save_customfield_value
 */
final class save_customfield_value_test extends advanced_testcase {
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

    /**
     * A plan-level narrative field can be saved and read back.
     *
     * @return void
     */
    public function test_saves_plan_field_value(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $handler = plan_handler::create();
        $fieldid = $this->field_id($handler, 'coursedescription');

        $result = save_customfield_value::execute(
            $syllabus->cmid,
            'plan',
            $syllabus->id,
            $fieldid,
            '<p>Course description content</p>',
            0
        );
        $result = external_api::clean_returnvalue(save_customfield_value::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $content = $handler->export_instance_data_object($syllabus->id);
        $this->assertStringContainsString('Course description content', $content->coursedescription);
    }

    /**
     * A week id that belongs to a different syllabus instance cannot be targeted.
     *
     * @return void
     */
    public function test_cross_instance_instanceid_rejected(): void {
        $coursea = $this->getDataGenerator()->create_course();
        $syllabusa = $this->getDataGenerator()->create_module('syllabus', ['course' => $coursea->id]);
        $teachera = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teachera->id, $coursea->id, 'editingteacher');

        $courseb = $this->getDataGenerator()->create_course();
        $syllabusb = $this->getDataGenerator()->create_module('syllabus', ['course' => $courseb->id]);
        $teacherb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacherb->id, $courseb->id, 'editingteacher');

        $this->setUser($teacherb);
        $weekb = save_week::execute($syllabusb->cmid, 0, 'Week B', null, null, null, null, null, null);

        $handler = week_handler::create();
        $fieldid = $this->field_id($handler, 'details');

        $this->setUser($teachera);
        $this->expectException(moodle_exception::class);
        save_customfield_value::execute($syllabusa->cmid, 'week', $weekb['weekid'], $fieldid, 'Hijacked', 0);
    }

    /**
     * An unknown area is rejected.
     *
     * @return void
     */
    public function test_invalid_area_rejected(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(moodle_exception::class);
        save_customfield_value::execute($syllabus->cmid, 'bogus', $syllabus->id, 1, 'Value', 0);
    }

    /**
     * A field id that does not belong to the requested area is rejected.
     *
     * @return void
     */
    public function test_field_from_another_area_rejected(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $weekfieldid = $this->field_id(week_handler::create(), 'details');

        $this->expectException(moodle_exception::class);
        save_customfield_value::execute($syllabus->cmid, 'plan', $syllabus->id, $weekfieldid, 'Value', 0);
    }

    /**
     * A user without mod/syllabus:submit cannot save a value.
     *
     * @return void
     */
    public function test_requires_capability(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        // Force visible so the capability check itself is what's under test, not the
        // separate (and equally intentional) gate that blocks access to a hidden activity.
        $DB->set_field('course_modules', 'visible', 1, ['id' => $syllabus->cmid]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $fieldid = $this->field_id(plan_handler::create(), 'coursedescription');

        $this->expectException(\required_capability_exception::class);
        save_customfield_value::execute($syllabus->cmid, 'plan', $syllabus->id, $fieldid, 'Value', 0);
    }
}
