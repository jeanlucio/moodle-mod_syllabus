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

/**
 * Tests for plan_reader, beyond what tab_visibility_test.php already exercises indirectly.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use advanced_testcase;
use mod_syllabus\customfield\plan_handler;

/**
 * plan_narrative() and export_editable_fields() are only reached today through tab_full_plan's
 * own export_for_template() branches, which tab_visibility_test.php never drives into edit
 * mode with real saved fields — these tests call them directly instead.
 *
 * @covers \mod_syllabus\output\plan_reader
 */
final class plan_reader_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Sets a Custom Field value directly, mirroring save_customfield_value::execute().
     *
     * @param \core_customfield\handler $handler
     * @param int $instanceid
     * @param string $shortname
     * @param string $value
     * @return void
     */
    private function set_customfield_value(
        \core_customfield\handler $handler,
        int $instanceid,
        string $shortname,
        string $value
    ): void {
        foreach ($handler->get_instance_data($instanceid, true) as $datacontroller) {
            if ($datacontroller->get_field()->get('shortname') !== $shortname) {
                continue;
            }
            if (!$datacontroller->get('id')) {
                $datacontroller->set('contextid', $handler->get_instance_context($instanceid)->id);
            }
            $fakeform = new \stdClass();
            $fakeform->{$datacontroller->get_form_element_name()} = [
                'text' => $value, 'format' => FORMAT_HTML, 'itemid' => 0,
            ];
            $datacontroller->instance_form_save($fakeform);
            return;
        }
        $this->fail("Seeded field '{$shortname}' not found.");
    }

    /**
     * plan_narrative() returns the plan-level Custom Field values, unfiltered.
     *
     * @return void
     */
    public function test_plan_narrative_returns_plan_level_fields(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $this->set_customfield_value(plan_handler::create(), $syllabus->id, 'coursedescription', 'A narrative value.');

        $reader = new plan_reader($syllabus);
        $narrative = $reader->plan_narrative();

        $this->assertStringContainsString('A narrative value.', $narrative->coursedescription);
    }

    /**
     * export_editable_fields() only exports textarea-type fields, each with its editable box
     * pre-filled, its own element id, its formatted description and the isplanfield flag set
     * for the plan area only. The description is the field's Custom Field property as-is —
     * combining the short summary and the full model guidance behind a disclosure is
     * help_text_builder's job at seed time, not this method's.
     *
     * @return void
     */
    public function test_export_editable_fields_shapes_textarea_fields_only(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $this->set_customfield_value(plan_handler::create(), $syllabus->id, 'coursedescription', 'Editable content.');

        $datacontrollers = plan_handler::create()->get_instance_data($syllabus->id, true);
        $reader = new plan_reader($syllabus);
        $fields = $reader->export_editable_fields($datacontrollers, 'plan');

        $this->assertNotEmpty($fields);
        foreach ($fields as $field) {
            $this->assertTrue($field->isplanfield);
            $this->assertSame('plan', $field->area);
            $this->assertStringContainsString('syllabus-field-', $field->elementid);
        }
        $descriptionfield = current(array_filter($fields, fn ($f) => $f->name === get_string('coursedescription', 'mod_syllabus')));
        $this->assertNotFalse($descriptionfield);
        $this->assertStringContainsString('Editable content.', $descriptionfield->text);
        $this->assertStringContainsString(
            get_string('viewmodelguidance', 'mod_syllabus'),
            $descriptionfield->description
        );
    }

    /**
     * export_editable_fields() on a week area exports fields tagged isplanfield => false.
     *
     * @return void
     */
    public function test_export_editable_fields_week_area_is_not_flagged_as_plan_field(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $now = time();
        $weekid = $DB->insert_record('syllabus_weeks', [
            'syllabusid'   => $syllabus->id,
            'title'        => 'Week 1',
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $datacontrollers = \mod_syllabus\customfield\week_handler::create()->get_instance_data($weekid, true);
        $reader = new plan_reader($syllabus);
        $fields = $reader->export_editable_fields($datacontrollers, 'week');

        $this->assertNotEmpty($fields);
        foreach ($fields as $field) {
            $this->assertFalse($field->isplanfield);
            $this->assertSame('week', $field->area);
        }
    }

    /**
     * structural_help() returns the formatted description of every field in the 'help' area,
     * keyed by shortname — the guidance for structural fields (Characterisation, a week's
     * workload/period, Synchronous meeting, an activity's type/category, Final assessment)
     * that have no narrative Custom Field of their own.
     *
     * @return void
     */
    public function test_structural_help_returns_all_five_fields(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);

        $reader = new plan_reader($syllabus);
        $help = $reader->structural_help();

        $this->assertEqualsCanonicalizing(
            ['characterisation', 'weekplanning', 'syncmeeting', 'activitytype', 'finalassessment'],
            array_keys($help)
        );
        $this->assertStringContainsString(get_string('viewmodelguidance', 'mod_syllabus'), $help['characterisation']);
    }
}
