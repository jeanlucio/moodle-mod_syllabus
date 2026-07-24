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

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_syllabus\local\plan_state_manager;
use mod_syllabus\local\structural_change_detector;

/**
 * External function to autosave the plan's Final assessment block.
 *
 * Unlike save_plan_details (Characterisation), these fields define the discipline's recovery
 * activity — its own schedule and points — so a change here reopens an approved plan for
 * review, the same treatment save_activity already gives a regular activity's structural
 * fields.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_final_assessment extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'                     => new external_value(PARAM_INT, 'Course module ID'),
            'finalassessmenttitle'     => new external_value(PARAM_TEXT, 'Final assessment title', VALUE_DEFAULT, null),
            'finalassessmenttype'      => new external_value(PARAM_TEXT, 'Final assessment type', VALUE_DEFAULT, null),
            'finalassessmentstartdate' => new external_value(PARAM_INT, 'Start date (timestamp)', VALUE_DEFAULT, null),
            'finalassessmentenddate'   => new external_value(PARAM_INT, 'End date (timestamp)', VALUE_DEFAULT, null),
            'finalassessmentpoints'    => new external_value(PARAM_FLOAT, 'Points', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Save the plan's Final assessment fields.
     *
     * @param int $cmid Course module ID.
     * @param string|null $finalassessmenttitle Final assessment title.
     * @param string|null $finalassessmenttype Final assessment type.
     * @param int|null $finalassessmentstartdate Start date.
     * @param int|null $finalassessmentenddate End date.
     * @param float|null $finalassessmentpoints Points.
     * @return array Result with success status.
     */
    public static function execute(
        int $cmid,
        ?string $finalassessmenttitle,
        ?string $finalassessmenttype,
        ?int $finalassessmentstartdate,
        ?int $finalassessmentenddate,
        ?float $finalassessmentpoints
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'                     => $cmid,
            'finalassessmenttitle'     => $finalassessmenttitle,
            'finalassessmenttype'      => $finalassessmenttype,
            'finalassessmentstartdate' => $finalassessmentstartdate,
            'finalassessmentenddate'   => $finalassessmentenddate,
            'finalassessmentpoints'    => $finalassessmentpoints,
        ]);

        $cm = get_coursemodule_from_id('syllabus', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/syllabus:submit', $context);

        $plan = $DB->get_record('syllabus', ['id' => $cm->instance], '*', MUST_EXIST);
        plan_state_manager::require_structural_editable($plan);

        $fields = [
            'finalassessmenttitle', 'finalassessmenttype',
            'finalassessmentstartdate', 'finalassessmentenddate', 'finalassessmentpoints',
        ];
        $submitted = array_intersect_key($params, array_flip($fields));

        // Normalise both sides to the same fixed-decimal string the NUMBER(10,2) column
        // stores before comparing: a submitted PHP float stringifies without trailing zeros
        // (e.g. "100"), while the column round-trips through the DB with 2 decimals (e.g.
        // "100.00") — structural_change_detector::changed() would otherwise report a
        // spurious change on every resave.
        $comparable = $submitted;
        $comparable['finalassessmentpoints'] = self::format_points($submitted['finalassessmentpoints']);
        $existing = clone $plan;
        $existing->finalassessmentpoints = self::format_points($plan->finalassessmentpoints);

        $changed = structural_change_detector::changed($existing, $comparable, $fields);

        $record = (object) array_merge(['id' => $plan->id], $submitted);
        $record->timemodified = time();
        $DB->update_record('syllabus', $record);

        if ($changed) {
            plan_state_manager::reopen_for_structural_change($plan->id);
        }

        return [
            'success' => true,
        ];
    }

    /**
     * Formats a points value as a fixed 2-decimal string, matching how the NUMBER(10,2)
     * column round-trips through the DB — see the comment above its call site.
     *
     * @param float|string|null $points
     * @return string|null
     */
    private static function format_points($points): ?string {
        return $points === null ? null : number_format((float) $points, 2, '.', '');
    }

    /**
     * Describe the return value of this function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
        ]);
    }
}
