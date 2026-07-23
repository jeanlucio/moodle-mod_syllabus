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
use invalid_parameter_exception;
use mod_syllabus\event\plan_approved;
use mod_syllabus\event\plan_changes_requested;
use mod_syllabus\local\plan_state_manager;

/**
 * External function for a coordinator to approve a plan or request changes.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_plan extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'     => new external_value(PARAM_INT, 'Course module ID'),
            'decision' => new external_value(PARAM_ALPHANUMEXT, 'Either "approved" or "changes_requested"'),
            'reason'   => new external_value(PARAM_TEXT, 'Justification, required for changes_requested', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Approve the plan or send it back with requested changes, triggering the matching event.
     *
     * @param int $cmid Course module ID.
     * @param string $decision Either "approved" or "changes_requested".
     * @param string $reason Justification, required for changes_requested.
     * @return array Result with the new status and success flag.
     */
    public static function execute(int $cmid, string $decision, string $reason = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'     => $cmid,
            'decision' => $decision,
            'reason'   => $reason,
        ]);

        $cm = get_coursemodule_from_id('syllabus', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/syllabus:review', $context);

        $plan = $DB->get_record('syllabus', ['id' => $cm->instance], '*', MUST_EXIST);

        if ($params['decision'] === plan_state_manager::STATUS_APPROVED) {
            plan_state_manager::approve($plan->id, (int) $USER->id);
            plan_approved::create([
                'objectid' => $plan->id,
                'context'  => $context,
            ])->trigger();
        } else if ($params['decision'] === plan_state_manager::STATUS_CHANGES_REQUESTED) {
            if (trim($params['reason']) === '') {
                throw new invalid_parameter_exception('A reason is required when requesting changes.');
            }
            plan_state_manager::request_changes($plan->id, (int) $USER->id, $params['reason']);
            plan_changes_requested::create([
                'objectid' => $plan->id,
                'context'  => $context,
                'other'    => ['reason' => $params['reason']],
            ])->trigger();
        } else {
            throw new invalid_parameter_exception('decision must be "approved" or "changes_requested".');
        }

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
