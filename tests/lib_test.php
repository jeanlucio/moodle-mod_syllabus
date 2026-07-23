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

/**
 * Unit tests for mod_syllabus's lib.php functions.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::syllabus_add_instance
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
     * @return void
     */
    public function test_add_instance_forces_course_module_hidden(): void {
        $course = $this->getDataGenerator()->create_course();

        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id], ['visible' => 1]);

        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $this->assertEquals(0, $cm->visible);
    }
}
