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
use stdClass;

/**
 * External function to autosave the plan-level Characterisation and Presentation fields.
 *
 * Unlike save_week/save_activity, this never gates on
 * plan_state_manager::require_structural_editable() nor reopens an approved plan for
 * review: these fields (academic period, course dates, declared total workload, the
 * presentation video link) are plan-level metadata, not schedule-defining structure — the
 * same "content stays editable" treatment already given to narrative Custom Fields (SCOPE
 * §3.1's hybrid rule only covers week/activity structural fields).
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_plan_details extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'                 => new external_value(PARAM_INT, 'Course module ID'),
            'academicperiod'       => new external_value(PARAM_TEXT, 'Academic year/term, e.g. 2026.1', VALUE_DEFAULT, null),
            'coursestartdate'      => new external_value(PARAM_INT, 'Course period start (timestamp)', VALUE_DEFAULT, null),
            'courseenddate'        => new external_value(PARAM_INT, 'Course period end (timestamp)', VALUE_DEFAULT, null),
            'totalduration'        => new external_value(PARAM_INT, 'Declared total workload in hours', VALUE_DEFAULT, null),
            'presentationvideourl' => new external_value(
                PARAM_URL,
                'Teacher and course presentation video link',
                VALUE_DEFAULT,
                null
            ),
        ]);
    }

    /**
     * Save the plan's Characterisation and Presentation fields.
     *
     * @param int $cmid Course module ID.
     * @param string|null $academicperiod Academic year/term.
     * @param int|null $coursestartdate Course period start.
     * @param int|null $courseenddate Course period end.
     * @param int|null $totalduration Declared total workload in hours.
     * @param string|null $presentationvideourl Presentation video link.
     * @return array Result with success status.
     */
    public static function execute(
        int $cmid,
        ?string $academicperiod,
        ?int $coursestartdate,
        ?int $courseenddate,
        ?int $totalduration,
        ?string $presentationvideourl
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'                 => $cmid,
            'academicperiod'       => $academicperiod,
            'coursestartdate'      => $coursestartdate,
            'courseenddate'        => $courseenddate,
            'totalduration'        => $totalduration,
            'presentationvideourl' => $presentationvideourl,
        ]);

        $cm = get_coursemodule_from_id('syllabus', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/syllabus:submit', $context);

        $record = new stdClass();
        $record->id = $cm->instance;
        $record->academicperiod = $params['academicperiod'];
        $record->coursestartdate = $params['coursestartdate'];
        $record->courseenddate = $params['courseenddate'];
        $record->totalduration = $params['totalduration'];
        $record->presentationvideourl = $params['presentationvideourl'];
        $record->timemodified = time();
        $DB->update_record('syllabus', $record);

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
            'success' => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
        ]);
    }
}
