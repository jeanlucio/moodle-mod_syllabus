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

namespace mod_syllabus\local;

use advanced_testcase;

/**
 * Unit tests for structural_change_detector.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\local\structural_change_detector
 */
final class structural_change_detector_test extends advanced_testcase {
    /**
     * A null existing record always counts as changed (a new row).
     *
     * @return void
     */
    public function test_new_row_is_always_changed(): void {
        $this->assertTrue(structural_change_detector::changed(null, ['title' => 'X'], ['title']));
    }

    /**
     * Identical values are not a change.
     *
     * @return void
     */
    public function test_identical_values_are_not_changed(): void {
        $existing = (object) ['title' => 'Week 1', 'duration' => 60];
        $submitted = ['title' => 'Week 1', 'duration' => 60];

        $this->assertFalse(structural_change_detector::changed($existing, $submitted, ['title', 'duration']));
    }

    /**
     * A difference in any compared field counts as a change.
     *
     * @return void
     */
    public function test_a_single_differing_field_is_changed(): void {
        $existing = (object) ['title' => 'Week 1', 'duration' => 60];
        $submitted = ['title' => 'Week 1', 'duration' => 90];

        $this->assertTrue(structural_change_detector::changed($existing, $submitted, ['title', 'duration']));
    }

    /**
     * A null stored value compared with a null submitted value is not a change.
     *
     * @return void
     */
    public function test_null_compared_with_null_is_not_changed(): void {
        $existing = (object) ['duration' => null];
        $submitted = ['duration' => null];

        $this->assertFalse(structural_change_detector::changed($existing, $submitted, ['duration']));
    }
}
