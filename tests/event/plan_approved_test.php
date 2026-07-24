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
 * Tests for the plan_approved event.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\event;

use advanced_testcase;
use coding_exception;
use context_module;

/**
 * Tests for the plan_approved event class itself.
 *
 * None of this is covered by observer_test.php — that file only asserts on the observer's
 * side effects, never on the event object's own name/description/url/validation.
 *
 * @coversDefaultClass \mod_syllabus\event\plan_approved
 */
final class plan_approved_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Builds a real plan_approved event against a real course module context.
     *
     * @return plan_approved
     */
    private function build_event(): plan_approved {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        return plan_approved::create([
            'objectid' => $syllabus->id,
            'context'  => context_module::instance($cm->id),
        ]);
    }

    /**
     * The event name, description and URL are all built correctly.
     *
     * @covers ::get_name
     * @covers ::get_description
     * @covers ::get_url
     * @covers ::get_objectid_mapping
     * @return void
     */
    public function test_name_description_url_and_mapping(): void {
        $this->setAdminUser();
        $event = $this->build_event();

        $this->assertSame(get_string('eventplanapproved', 'mod_syllabus'), plan_approved::get_name());
        $this->assertStringContainsString((string) $event->objectid, $event->get_description());
        $this->assertStringContainsString('approved', $event->get_description());
        $this->assertStringContainsString('/mod/syllabus/view.php', $event->get_url()->out(false));
        $this->assertSame(['db' => 'syllabus', 'restore' => 'syllabus'], plan_approved::get_objectid_mapping());
    }

    /**
     * Creating the event at anything other than a module context fails validation —
     * validate_data() runs at the end of create() itself, before the event is ever triggered.
     *
     * @covers ::validate_data
     * @return void
     */
    public function test_validate_data_requires_module_context(): void {
        $this->setAdminUser();
        $this->expectException(coding_exception::class);

        plan_approved::create([
            'objectid' => 1,
            'context'  => \context_system::instance(),
        ]);
    }
}
