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

namespace mod_syllabus\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_syllabus\local\help_text_builder;

/**
 * External function to reset one Custom Field's description back to the plugin's seeded
 * default text — an explicit admin action, not the automatic migration db/upgrade.php runs.
 *
 * Unlike an upgrade step, which only ever touches a field whose current description exactly
 * matches a known "not yet customised" signature (never overwriting a real institutional
 * customisation silently), this deliberately overwrites unconditionally: the admin's own
 * click is the confirmation that overwriting is what they want.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reset_field_description extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fieldid' => new external_value(PARAM_INT, 'Custom Field id'),
        ]);
    }

    /**
     * Reset a Custom Field's description to its seeded default.
     *
     * @param int $fieldid Custom Field id.
     * @return array Result with success status.
     */
    public static function execute(int $fieldid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['fieldid' => $fieldid]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('mod/syllabus:review', $context);

        $field = $DB->get_record_sql(
            "SELECT f.id, f.shortname
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'mod_syllabus' AND f.id = :fieldid",
            ['fieldid' => $params['fieldid']],
            MUST_EXIST
        );

        $DB->update_record('customfield_field', (object) [
            'id'                => $field->id,
            'description'       => help_text_builder::build($field->shortname),
            'descriptionformat' => FORMAT_HTML,
        ]);

        return [
            'success' => true,
        ];
    }

    /**
     * Describe the return value of this function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the reset succeeded'),
        ]);
    }
}
