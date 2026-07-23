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

use coding_exception;
use context_module;
use core_customfield\handler;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;
use stdClass;

/**
 * External function to autosave the value of one narrative Custom Field.
 *
 * Only supports `customfield_textarea` fields, the only type mod_syllabus seeds or exposes
 * through managefields.php — a coordinator who adds a differently-typed field through the
 * generic Custom Fields admin UI would need this endpoint extended to match, not silently
 * mishandled.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_customfield_value extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'area'       => new external_value(PARAM_ALPHA, 'Custom field area: plan, week or activity'),
            'instanceid' => new external_value(PARAM_INT, 'Syllabus/week/activity ID owning the value'),
            'fieldid'    => new external_value(PARAM_INT, 'Custom field ID'),
            'value'      => new external_value(PARAM_RAW, 'HTML content of the field'),
            'itemid'     => new external_value(PARAM_INT, 'Draft file area ID for embedded files'),
        ]);
    }

    /**
     * Save the current value of one narrative Custom Field.
     *
     * @param int $cmid Course module ID.
     * @param string $area Custom field area: plan, week or activity.
     * @param int $instanceid Syllabus/week/activity ID owning the value.
     * @param int $fieldid Custom field ID.
     * @param string $value HTML content of the field.
     * @param int $itemid Draft file area ID for embedded files.
     * @return array Result with success status and timestamp.
     */
    public static function execute(
        int $cmid,
        string $area,
        int $instanceid,
        int $fieldid,
        string $value,
        int $itemid
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'       => $cmid,
            'area'       => $area,
            'instanceid' => $instanceid,
            'fieldid'    => $fieldid,
            'value'      => $value,
            'itemid'     => $itemid,
        ]);

        $cm = get_coursemodule_from_id('syllabus', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/syllabus:submit', $context);

        $allowedareas = ['plan', 'week', 'activity'];
        if (!in_array($params['area'], $allowedareas, true)) {
            throw new moodle_exception('invalidarea', 'mod_syllabus');
        }

        $handler = handler::get_handler('mod_syllabus', $params['area']);
        if (!$handler->belongs_to_syllabus($params['instanceid'], $cm->instance)) {
            throw new moodle_exception('invalidarea', 'mod_syllabus');
        }

        $fielddatas = $handler->get_instance_data($params['instanceid'], true);
        if (!array_key_exists($params['fieldid'], $fielddatas)) {
            throw new moodle_exception('invalidfield', 'mod_syllabus');
        }
        $datacontroller = $fielddatas[$params['fieldid']];

        if ($datacontroller->get_field()->get('type') !== 'textarea') {
            throw new coding_exception('save_customfield_value only supports customfield_textarea fields.');
        }

        if (!$datacontroller->get('id')) {
            // Mirrors handler::instance_form_save(), which this single-field call bypasses:
            // a brand new data row needs its contextid set before the first save() or the
            // underlying persistent rejects it as a required field.
            $datacontroller->set('contextid', $handler->get_instance_context($params['instanceid'])->id);
        }

        $now = time();
        $fakeform = new stdClass();
        $fakeform->{$datacontroller->get_form_element_name()} = [
            'text'   => $params['value'],
            'format' => FORMAT_HTML,
            'itemid' => $params['itemid'],
        ];
        $datacontroller->instance_form_save($fakeform);

        return [
            'success'  => true,
            'saved_at' => $now,
        ];
    }

    /**
     * Describe the return value of this function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
            'saved_at' => new external_value(PARAM_INT, 'Timestamp when saved'),
        ]);
    }
}
