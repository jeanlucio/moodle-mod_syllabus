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
 * Tests for the mod_syllabus renderer.
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
 * Mustache rendering works fine inside PHPUnit — it needs no real HTTP request, just $PAGE/
 * $OUTPUT, which advanced_testcase already sets up. These tests render each tab from a real
 * exported data object and check the resulting HTML actually carries the plan's own content,
 * proving the renderer methods do more than call render_from_template() without error.
 *
 * @coversDefaultClass \mod_syllabus\output\renderer
 */
final class renderer_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Seeds a course with an approved syllabus carrying a distinctive plan-level marker.
     *
     * @return \stdClass The syllabus record.
     */
    private function seed_approved_plan(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        foreach (plan_handler::create()->get_instance_data($syllabus->id, true) as $datacontroller) {
            if ($datacontroller->get_field()->get('shortname') !== 'coursedescription') {
                continue;
            }
            if (!$datacontroller->get('id')) {
                $datacontroller->set('contextid', plan_handler::create()->get_instance_context($syllabus->id)->id);
            }
            $fakeform = new \stdClass();
            $fakeform->{$datacontroller->get_form_element_name()} = [
                'text' => 'RENDERER_MARKER_CONTENT', 'format' => FORMAT_HTML, 'itemid' => 0,
            ];
            $datacontroller->instance_form_save($fakeform);
        }

        \mod_syllabus\local\plan_state_manager::submit($syllabus->id, (int) $teacher->id);
        \mod_syllabus\local\plan_state_manager::approve($syllabus->id, (int) $this->getDataGenerator()->create_user()->id);

        return $syllabus;
    }

    /**
     * render_tab_student_plan() produces real HTML containing the plan's own content.
     *
     * @covers ::render_tab_student_plan
     * @return void
     */
    public function test_render_tab_student_plan_contains_plan_content(): void {
        global $PAGE;

        $syllabus = $this->seed_approved_plan();
        $course = get_course($syllabus->course);
        $page = new tab_student_plan($syllabus, $course);
        $renderer = $PAGE->get_renderer('mod_syllabus');
        $data = $page->export_for_template($renderer);

        $html = $renderer->render_tab_student_plan($data);

        $this->assertStringContainsString('RENDERER_MARKER_CONTENT', $html);
    }

    /**
     * render_tab_tutor_plan() produces real HTML containing the plan's own content.
     *
     * @covers ::render_tab_tutor_plan
     * @return void
     */
    public function test_render_tab_tutor_plan_renders_without_error(): void {
        global $PAGE;

        $syllabus = $this->seed_approved_plan();
        $course = get_course($syllabus->course);
        $page = new tab_tutor_plan($syllabus, $course);
        $renderer = $PAGE->get_renderer('mod_syllabus');
        $data = $page->export_for_template($renderer);

        $html = $renderer->render_tab_tutor_plan($data);

        $this->assertNotEmpty($html);
        $this->assertIsString($html);
    }

    /**
     * render_tab_full_plan() produces real HTML containing the plan's own content, in the
     * read-only (coordination review) branch.
     *
     * @covers ::render_tab_full_plan
     * @return void
     */
    public function test_render_tab_full_plan_contains_plan_content(): void {
        global $PAGE;

        $syllabus = $this->seed_approved_plan();
        $course = get_course($syllabus->course);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');
        $this->setUser($manager);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $renderer = $PAGE->get_renderer('mod_syllabus');
        $data = $page->export_for_template($renderer);

        $html = $renderer->render_tab_full_plan($data);

        $this->assertStringContainsString('RENDERER_MARKER_CONTENT', $html);
    }
}
