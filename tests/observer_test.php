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
use core\event\course_module_updated;
use mod_syllabus\local\plan_state_manager;

/**
 * Unit tests for the mod_syllabus event observer.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\observer
 */
final class observer_test extends advanced_testcase {
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
     * Manually flips course_modules.visible the same way the course page's own show/hide
     * control does (core_courseformat\local\stateactions::cm_show()/cm_hide()), bypassing
     * mod_form entirely, then fires the same event that control fires afterwards.
     *
     * @param \stdClass $cm Course module record.
     * @param int $visible New visible value to force.
     * @return void
     */
    private function bypass_form_and_toggle_visibility(\stdClass $cm, int $visible): void {
        global $DB;

        $DB->set_field('course_modules', 'visible', $visible, ['id' => $cm->id]);
        $DB->set_field('course_modules', 'visibleoncoursepage', $visible, ['id' => $cm->id]);

        $modcontext = \context_module::instance($cm->id);
        course_module_updated::create_from_cm($cm, $modcontext)->trigger();
    }

    /**
     * A draft plan manually shown via the course page (bypassing the frozen form field) is
     * hidden again by the observer — this is the exact bug reported live: the "Availability"
     * field is frozen in mod_form.php, but the course page's own show/hide control never goes
     * through that form at all.
     *
     * @return void
     */
    public function test_manually_showing_a_draft_plan_is_reverted(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $this->assertEquals(0, $cm->visible, 'Sanity check: a new plan must start hidden.');

        $this->bypass_form_and_toggle_visibility($cm, 1);

        $cmrow = $DB->get_record('course_modules', ['id' => $cm->id], '*', MUST_EXIST);
        $this->assertEquals(0, $cmrow->visible, 'The observer must hide a draft plan shown outside the workflow.');
    }

    /**
     * An approved plan manually hidden via the course page is shown again by the observer.
     *
     * @return void
     */
    public function test_manually_hiding_an_approved_plan_is_reverted(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        $this->bypass_form_and_toggle_visibility($cm, 0);

        $cmrow = $DB->get_record('course_modules', ['id' => $cm->id], '*', MUST_EXIST);
        $this->assertEquals(1, $cmrow->visible, 'The observer must re-show an approved plan hidden outside the workflow.');
    }

    /**
     * The observer ignores course modules belonging to other module types entirely.
     *
     * @return void
     */
    public function test_ignores_other_module_types(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $this->bypass_form_and_toggle_visibility($cm, 1);

        $cmrow = $DB->get_record('course_modules', ['id' => $cm->id], '*', MUST_EXIST);
        $this->assertEquals(1, $cmrow->visible, 'The observer must not touch course modules of other module types.');
    }
}
