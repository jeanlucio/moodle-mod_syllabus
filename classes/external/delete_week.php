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
use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\week_handler;
use mod_syllabus\local\plan_state_manager;

/**
 * External function to delete a week and its activities from a syllabus plan.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_week extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT, 'Course module ID'),
            'weekid' => new external_value(PARAM_INT, 'Week ID to delete'),
        ]);
    }

    /**
     * Delete a week, its activities, and all their Custom Field data.
     *
     * @param int $cmid Course module ID.
     * @param int $weekid Week ID.
     * @return array Result with success status.
     */
    public static function execute(int $cmid, int $weekid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'   => $cmid,
            'weekid' => $weekid,
        ]);

        $cm = get_coursemodule_from_id('syllabus', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/syllabus:submit', $context);

        $plan = $DB->get_record('syllabus', ['id' => $cm->instance], '*', MUST_EXIST);
        plan_state_manager::require_structural_editable($plan);

        $week = $DB->get_record(
            'syllabus_weeks',
            ['id' => $params['weekid'], 'syllabusid' => $plan->id],
            '*',
            MUST_EXIST
        );

        $activityids = $DB->get_fieldset_select('syllabus_activities', 'id', 'weekid = ?', [$week->id]);
        if ($activityids) {
            $activityhandler = activity_handler::create();
            foreach ($activityids as $activityid) {
                $activityhandler->delete_instance($activityid);
            }
            $DB->delete_records('syllabus_activities', ['weekid' => $week->id]);
        }

        week_handler::create()->delete_instance($week->id);
        $DB->delete_records('syllabus_weeks', ['id' => $week->id]);

        plan_state_manager::reopen_for_structural_change($plan->id);

        return ['success' => true];
    }

    /**
     * Describe the return value of this function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the deletion succeeded'),
        ]);
    }
}
