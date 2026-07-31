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
 * Unit tests for the mod_syllabus_save_review_note external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\save_review_note
 */
final class save_review_note_test extends advanced_testcase {
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
     * Creates a course with a syllabus instance and a coordinator (manager) enrolled in it.
     *
     * @return array [\stdClass $syllabus, \stdClass $coordinator]
     */
    private function create_plan_with_coordinator(): array {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $coordinator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($coordinator->id, $course->id, 'manager');
        $this->setUser($coordinator);

        return [$syllabus, $coordinator];
    }

    /**
     * A coordinator can leave a note on a plan-level field, and saving again with new text
     * updates the same row rather than creating a second one.
     *
     * @return void
     */
    public function test_creates_and_updates_note(): void {
        global $DB;

        [$syllabus, $coordinator] = $this->create_plan_with_coordinator();
        $fieldid = $this->field_id(plan_handler::create(), 'coursedescription');

        $result = save_review_note::execute($syllabus->cmid, 'plan', $syllabus->id, $fieldid, 'Please expand this.');
        $result = external_api::clean_returnvalue(save_review_note::execute_returns(), $result);
        $this->assertTrue($result['success']);

        $note = $DB->get_record('syllabus_review_notes', ['syllabusid' => $syllabus->id]);
        $this->assertSame('Please expand this.', $note->note);
        $this->assertEquals($coordinator->id, $note->reviewerid);

        save_review_note::execute($syllabus->cmid, 'plan', $syllabus->id, $fieldid, 'Updated note.');

        $this->assertEquals(1, $DB->count_records('syllabus_review_notes', ['syllabusid' => $syllabus->id]));
        $updated = $DB->get_record('syllabus_review_notes', ['id' => $note->id], '*', MUST_EXIST);
        $this->assertSame('Updated note.', $updated->note);
    }

    /**
     * Saving an empty note clears (deletes) an existing one.
     *
     * @return void
     */
    public function test_empty_note_clears_existing_note(): void {
        global $DB;

        [$syllabus] = $this->create_plan_with_coordinator();
        $fieldid = $this->field_id(plan_handler::create(), 'coursedescription');

        save_review_note::execute($syllabus->cmid, 'plan', $syllabus->id, $fieldid, 'A note.');
        $this->assertEquals(1, $DB->count_records('syllabus_review_notes', ['syllabusid' => $syllabus->id]));

        save_review_note::execute($syllabus->cmid, 'plan', $syllabus->id, $fieldid, '');

        $this->assertEquals(0, $DB->count_records('syllabus_review_notes', ['syllabusid' => $syllabus->id]));
    }

    /**
     * Saving an empty note when none exists yet is a harmless no-op, not an error.
     *
     * @return void
     */
    public function test_empty_note_with_no_existing_note_is_a_noop(): void {
        global $DB;

        [$syllabus] = $this->create_plan_with_coordinator();
        $fieldid = $this->field_id(plan_handler::create(), 'coursedescription');

        $result = save_review_note::execute($syllabus->cmid, 'plan', $syllabus->id, $fieldid, '');
        $result = external_api::clean_returnvalue(save_review_note::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $DB->count_records('syllabus_review_notes', ['syllabusid' => $syllabus->id]));
    }

    /**
     * A week id that belongs to a different syllabus instance cannot be targeted.
     *
     * @return void
     */
    public function test_cross_instance_instanceid_rejected(): void {
        $coursea = $this->getDataGenerator()->create_course();
        $syllabusa = $this->getDataGenerator()->create_module('syllabus', ['course' => $coursea->id]);
        $coordinatora = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($coordinatora->id, $coursea->id, 'manager');

        $courseb = $this->getDataGenerator()->create_course();
        $syllabusb = $this->getDataGenerator()->create_module('syllabus', ['course' => $courseb->id]);
        $teacherb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacherb->id, $courseb->id, 'editingteacher');

        $this->setUser($teacherb);
        $weekb = save_week::execute($syllabusb->cmid, 0, 'Week B', null, null, null, null, null, null, 1);

        $fieldid = $this->field_id(week_handler::create(), 'details');

        $this->setUser($coordinatora);
        $this->expectException(moodle_exception::class);
        save_review_note::execute($syllabusa->cmid, 'week', $weekb['weekid'], $fieldid, 'Hijacked note');
    }

    /**
     * An unknown area is rejected.
     *
     * @return void
     */
    public function test_invalid_area_rejected(): void {
        [$syllabus] = $this->create_plan_with_coordinator();

        $this->expectException(moodle_exception::class);
        save_review_note::execute($syllabus->cmid, 'bogus', $syllabus->id, 1, 'Note');
    }

    /**
     * A field id that does not belong to the requested area is rejected.
     *
     * @return void
     */
    public function test_field_from_another_area_rejected(): void {
        [$syllabus] = $this->create_plan_with_coordinator();
        $weekfieldid = $this->field_id(week_handler::create(), 'details');

        $this->expectException(moodle_exception::class);
        save_review_note::execute($syllabus->cmid, 'plan', $syllabus->id, $weekfieldid, 'Note');
    }

    /**
     * A user without mod/syllabus:review cannot leave a note, even the plan's own author.
     *
     * @return void
     */
    public function test_requires_review_capability(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        // Force visible so the capability check itself is what's under test, not the
        // separate (and equally intentional) gate that blocks access to a hidden activity.
        $DB->set_field('course_modules', 'visible', 1, ['id' => $syllabus->cmid]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $fieldid = $this->field_id(plan_handler::create(), 'coursedescription');

        $this->expectException(\required_capability_exception::class);
        save_review_note::execute($syllabus->cmid, 'plan', $syllabus->id, $fieldid, 'Note');
    }
}
