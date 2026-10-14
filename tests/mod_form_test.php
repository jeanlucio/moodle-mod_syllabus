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
 * Tests for the activity settings form.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus;

/**
 * The activity name is stored in a 255-character column, so the form must refuse a longer one.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_syllabus_mod_form
 */
final class mod_form_test extends \advanced_testcase {
    /**
     * The name field carries a maxlength rule that fits its column.
     */
    public function test_the_name_field_has_a_maxlength_rule_that_fits_the_column(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/mod/syllabus/mod_form.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $instance->id);
        $PAGE->set_course($course);
        $form = new \mod_syllabus_mod_form((object) [
            'instance' => $instance->id, 'id' => $cm->id, 'course' => $course->id,
        ], 0, $cm, $course);

        $quickform = (new \ReflectionProperty(\mod_syllabus_mod_form::class, '_form'))->getValue($form);
        $rules = (new \ReflectionProperty(\HTML_QuickForm::class, '_rules'))->getValue($quickform);
        $limits = [];
        foreach ($rules['name'] ?? [] as $rule) {
            if ($rule['type'] === 'maxlength') {
                $limits[] = (int) $rule['format'];
            }
        }

        $this->assertNotEmpty($limits, 'The name field has no maxlength rule.');
        $this->assertLessThanOrEqual(255, min($limits));
    }
}
