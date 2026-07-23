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
 * Backup task for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/syllabus/backup/moodle2/backup_syllabus_stepslib.php');

/**
 * Backup task for mod_syllabus.
 */
class backup_syllabus_activity_task extends backup_activity_task {
    /**
     * Define the specific steps for backup.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_syllabus_activity_structure_step('syllabus_structure', 'syllabus.xml'));
    }

    /**
     * Define the specific rules for the backup.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No specific settings.
    }

    /**
     * Encode the content links.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = "/(" . $base . "\/mod\/syllabus\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@SYLLABUSINDEX*$2@$', $content);

        $search = "/(" . $base . "\/mod\/syllabus\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@SYLLABUSVIEWBYID*$2@$', $content);

        return $content;
    }
}
