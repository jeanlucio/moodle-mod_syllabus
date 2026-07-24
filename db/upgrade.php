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
 * Upgrade steps for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs mod_syllabus's upgrade steps.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_syllabus_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026072510) {
        // A site that installed this plugin before the "<shortname>help" lang strings existed
        // never had its narrative Custom Fields' short description backfilled — xmldb_syllabus_
        // install() only runs once, at initial install, never again on upgrade. Fill in the
        // description (and its format) for any field still missing one, scoped to this
        // plugin's own three areas so a same-named field belonging to another component is
        // never touched. A field an institution already customised via managefields.php (a
        // non-empty description) is left exactly as it is.
        $shortnames = [
            'coursedescription', 'objectives', 'contents', 'methodology',
            'presentationscript', 'generalreferences',
            'details', 'supportmaterial', 'supplementarymaterial', 'interactiontools', 'notes',
            'studentinstructions', 'gradingcriteria', 'tutorguidance',
        ];
        [$shortnamesql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $params['component'] = 'mod_syllabus';
        $fields = $DB->get_records_sql(
            "SELECT f.id, f.shortname
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component
                AND f.shortname $shortnamesql
                AND (f.description IS NULL OR " . $DB->sql_compare_text('f.description') . " = '')",
            $params
        );
        foreach ($fields as $field) {
            $DB->update_record('customfield_field', (object) [
                'id' => $field->id,
                'description' => get_string($field->shortname . 'help', 'mod_syllabus'),
                'descriptionformat' => FORMAT_HTML,
            ]);
        }

        upgrade_mod_savepoint(true, 2026072510, 'syllabus');
    }

    return true;
}
