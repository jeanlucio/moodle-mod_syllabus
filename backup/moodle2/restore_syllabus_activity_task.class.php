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
 * Restore task for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/syllabus/backup/moodle2/restore_syllabus_stepslib.php');

/**
 * Restore task for mod_syllabus.
 */
class restore_syllabus_activity_task extends restore_activity_task {
    /**
     * Define the specific steps for restore.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_syllabus_activity_structure_step('syllabus_structure', 'syllabus.xml'));
    }

    /**
     * Define the specific rules for the restore.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No specific settings.
    }

    /**
     * Define the rules for decoding links.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('SYLLABUSINDEX', '/mod/syllabus/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('SYLLABUSVIEWBYID', '/mod/syllabus/view.php?id=$1', 'course_module');

        return $rules;
    }

    /**
     * Define the content that needs to be decoded.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('syllabus', ['intro'], 'syllabus');

        return $contents;
    }
}
