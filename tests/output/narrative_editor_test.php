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
 * Tests for the narrative_editor Tiny configuration builder.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use advanced_testcase;
use context_module;

/**
 * Building the shared Tiny config needs no real HTTP request — $PAGE/$OUTPUT from
 * advanced_testcase are enough, so this is directly unit-testable rather than Behat-only.
 *
 * @coversDefaultClass \mod_syllabus\output\narrative_editor
 */
final class narrative_editor_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A user whose preferred editor is the default (Tiny) is reported as Tiny-available.
     *
     * @covers ::is_tiny_available
     * @return void
     */
    public function test_is_tiny_available_true_for_default_editor(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertTrue(narrative_editor::is_tiny_available());
    }

    /**
     * A user who explicitly chose the plain textarea editor in their profile is reported as
     * not having Tiny available — the lazy-load apparatus must never attach for them.
     *
     * @covers ::is_tiny_available
     * @return void
     */
    public function test_is_tiny_available_false_for_plain_textarea_preference(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_user_preference('htmleditor', 'textarea', $user);

        $this->assertFalse(narrative_editor::is_tiny_available());
    }

    /**
     * base_config() builds a complete Tiny configuration object scoped to the given context.
     *
     * @covers ::base_config
     * @return void
     */
    public function test_base_config_builds_expected_structure(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $config = narrative_editor::base_config($context);

        $this->assertSame($context->id, $config->context);
        $this->assertSame(0, $config->draftitemid);
        $this->assertSame(current_language(), $config->currentLanguage);
        $this->assertIsBool($config->branding);
        $this->assertIsArray($config->placeholderSelectors);
        $this->assertNotEmpty($config->plugins);
    }
}
