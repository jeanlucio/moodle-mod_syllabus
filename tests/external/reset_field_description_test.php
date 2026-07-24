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
use mod_syllabus\local\help_text_builder;

/**
 * Unit tests for the mod_syllabus_reset_field_description external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\reset_field_description
 */
final class reset_field_description_test extends advanced_testcase {
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
     * A customised description is unconditionally overwritten by the seeded default — unlike
     * the db/upgrade.php migration steps, this is a deliberate, explicit admin action.
     *
     * @return void
     */
    public function test_resets_a_customised_description(): void {
        global $DB;

        $this->setAdminUser();
        $field = $DB->get_record('customfield_field', ['shortname' => 'objectives'], '*', MUST_EXIST);
        $DB->set_field('customfield_field', 'description', 'Custom institutional wording.', ['id' => $field->id]);

        $result = reset_field_description::execute($field->id);
        $result = external_api::clean_returnvalue(reset_field_description::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $updated = $DB->get_record('customfield_field', ['id' => $field->id], '*', MUST_EXIST);
        $this->assertSame(help_text_builder::build('objectives'), $updated->description);
    }

    /**
     * A user without mod/syllabus:review cannot reset a field description.
     *
     * @return void
     */
    public function test_requires_capability(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $field = $DB->get_record('customfield_field', ['shortname' => 'objectives'], '*', MUST_EXIST);

        $this->expectException(\required_capability_exception::class);
        reset_field_description::execute($field->id);
    }

    /**
     * A field id belonging to a different component cannot be reset through this function.
     *
     * @return void
     */
    public function test_rejects_a_field_from_a_different_component(): void {
        $this->setAdminUser();
        $handler = \core_course\customfield\course_handler::create();
        $categoryid = $handler->create_category('Other component category');
        $category = \core_customfield\category_controller::create($categoryid);
        $record = (object) [
            'shortname'  => 'unrelatedfield',
            'name'       => 'Unrelated field',
            'type'       => 'text',
            'categoryid' => $categoryid,
        ];
        $field = \core_customfield\field_controller::create(0, $record, $category);
        \core_customfield\api::save_field_configuration($field, $record);

        $this->expectException(\dml_missing_record_exception::class);
        reset_field_description::execute($field->get('id'));
    }
}
