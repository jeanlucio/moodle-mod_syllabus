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
 * Tests for the plan_teacher_name resolver.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use advanced_testcase;

/**
 * Tests for plan_teacher_name::resolve().
 *
 * @covers \mod_syllabus\output\plan_teacher_name
 */
final class plan_teacher_name_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Resolves the full name of the plan's submitter.
     *
     * @return void
     */
    public function test_resolve_returns_submitter_fullname(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $syllabus = (object) ['submittedby' => $user->id];

        $this->assertSame(fullname($user), plan_teacher_name::resolve($syllabus));
    }

    /**
     * A plan never submitted has no submittedby yet, so it resolves to an empty string.
     *
     * @return void
     */
    public function test_resolve_returns_empty_string_when_never_submitted(): void {
        $syllabus = (object) ['submittedby' => 0];

        $this->assertSame('', plan_teacher_name::resolve($syllabus));
    }

    /**
     * A submittedby pointing at a deleted/unknown user id resolves to an empty string.
     *
     * @return void
     */
    public function test_resolve_returns_empty_string_for_unknown_user(): void {
        $syllabus = (object) ['submittedby' => 999999];

        $this->assertSame('', plan_teacher_name::resolve($syllabus));
    }
}
