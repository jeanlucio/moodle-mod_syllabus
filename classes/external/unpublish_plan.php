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

/**
 * External function to pull an approved plan back to draft (see plan_state_manager::unpublish()).
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unpublish_plan extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Unpublish the plan.
     *
     * Callable by a coordinator (mod/syllabus:review), or by the author who submitted the
     * version currently live — never by an unrelated teacher who merely shares
     * mod/syllabus:submit on the course. This is the capability check plan_state_manager's own
     * docblock defers to its caller.
     *
     * @param int $cmid Course module ID.
     * @return array Result with the new status and success flag.
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('syllabus', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/syllabus:view', $context);

        $plan = $DB->get_record('syllabus', ['id' => $cm->instance], '*', MUST_EXIST);

        if ((int) $plan->submittedby !== (int) $USER->id) {
            require_capability('mod/syllabus:review', $context);
        }

        plan_state_manager::unpublish($plan->id, (int) $USER->id);

        return [
            'status'  => $DB->get_field('syllabus', 'status', ['id' => $plan->id]),
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
            'status'  => new external_value(PARAM_ALPHANUMEXT, 'The plan\'s new status'),
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
        ]);
    }
}
