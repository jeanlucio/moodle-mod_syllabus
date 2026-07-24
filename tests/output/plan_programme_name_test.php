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
 * Tests for the plan_programme_name resolver.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use advanced_testcase;

/**
 * Tests for plan_programme_name::resolve().
 *
 * @coversDefaultClass \mod_syllabus\output\plan_programme_name
 */
final class plan_programme_name_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Resolves the formatted name of an existing course category.
     *
     * @covers ::resolve
     * @return void
     */
    public function test_resolve_existing_category(): void {
        $category = $this->getDataGenerator()->create_category(['name' => 'Computer Science']);

        $this->assertSame('Computer Science', plan_programme_name::resolve((int) $category->id));
    }

    /**
     * A category id that no longer exists resolves to an empty string, never a warning.
     *
     * @covers ::resolve
     * @return void
     */
    public function test_resolve_unknown_category_returns_empty_string(): void {
        $this->assertSame('', plan_programme_name::resolve(999999));
    }
}
