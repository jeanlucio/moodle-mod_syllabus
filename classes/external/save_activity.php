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
 * External function to create or update an activity within a syllabus week.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_activity extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'weekid'     => new external_value(PARAM_INT, 'Week ID this activity belongs to'),
            'activityid' => new external_value(PARAM_INT, 'Activity ID (0 for new)', VALUE_DEFAULT, 0),
            'title'      => new external_value(PARAM_TEXT, 'Activity title'),
            'type'       => new external_value(PARAM_TEXT, 'Activity type', VALUE_DEFAULT, null),
            'category'   => new external_value(PARAM_TEXT, 'Activity category', VALUE_DEFAULT, null),
            'startdate'  => new external_value(PARAM_INT, 'Start date (timestamp)', VALUE_DEFAULT, null),
            'enddate'    => new external_value(PARAM_INT, 'End date (timestamp)', VALUE_DEFAULT, null),
            'points'     => new external_value(PARAM_FLOAT, 'Points', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Create or update an activity.
     *
     * @param int $cmid Course module ID.
     * @param int $weekid Week ID this activity belongs to.
     * @param int $activityid Activity ID (0 for new).
     * @param string $title Activity title.
     * @param string|null $type Activity type.
     * @param string|null $category Activity category.
     * @param int|null $startdate Start date.
     * @param int|null $enddate End date.
     * @param float|null $points Points.
     * @return array Result with activityid and success status.
     */
    public static function execute(
        int $cmid,
        int $weekid,
        int $activityid,
        string $title,
        ?string $type,
        ?string $category,
        ?int $startdate,
        ?int $enddate,
        ?float $points
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'       => $cmid,
            'weekid'     => $weekid,
            'activityid' => $activityid,
            'title'      => $title,
            'type'       => $type,
            'category'   => $category,
            'startdate'  => $startdate,
            'enddate'    => $enddate,
            'points'     => $points,
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

        $fields = ['title', 'type', 'category', 'startdate', 'enddate', 'points'];
        $submitted = array_intersect_key($params, array_flip($fields));

        $existing = null;
        if ($params['activityid'] > 0) {
            $existing = $DB->get_record(
                'syllabus_activities',
                ['id' => $params['activityid'], 'weekid' => $week->id],
                '*',
                MUST_EXIST
            );
        }
        $changed = structural_change_detector::changed($existing, $submitted, $fields);

        $now = time();
        if ($existing !== null) {
            $record = $existing;
            $record->title = $submitted['title'];
            $record->type = $submitted['type'];
            $record->category = $submitted['category'];
            $record->startdate = $submitted['startdate'];
            $record->enddate = $submitted['enddate'];
            $record->points = $submitted['points'];
            $record->timemodified = $now;
            $DB->update_record('syllabus_activities', $record);
            $resultid = $record->id;
        } else {
            $maxsort = $DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), -1) FROM {syllabus_activities} WHERE weekid = :wid',
                ['wid' => $week->id]
            );
            $record = (object) [
                'weekid'       => $week->id,
                'title'        => $submitted['title'],
                'type'         => $submitted['type'],
                'category'     => $submitted['category'],
                'startdate'    => $submitted['startdate'],
                'enddate'      => $submitted['enddate'],
                'points'       => $submitted['points'],
                'sortorder'    => $maxsort + 1,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
            $resultid = $DB->insert_record('syllabus_activities', $record);
        }

        if ($changed) {
            plan_state_manager::reopen_for_structural_change($plan->id);
        }

        return [
            'activityid' => $resultid,
            'success'    => true,
        ];
    }

    /**
     * Describe the return value of this function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activityid' => new external_value(PARAM_INT, 'ID of the saved activity'),
            'success'    => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
        ]);
    }
}
