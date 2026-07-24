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

namespace mod_syllabus\output;

use stdClass;

/**
 * Shared read-only week/activity exporter for the two read-only tabs (student, tutor) and
 * for Tab 1's read-only branch (coordination/admin reviewing without edit rights).
 *
 * The single `$showtutorfields` flag is what implements the week/activity portion of the
 * visibility matrix: with it false, interaction tools/observations (week level) and grading
 * criteria/tutor guidance (activity level) are never copied into the exported object at
 * all — the corresponding shared template (plan_week_read.mustache /
 * plan_activity_read.mustache) never receives them, so there is nothing to accidentally
 * leak by a template change.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_read_export {
    /**
     * Exports a read-only view of every week returned by plan_reader::weeks().
     *
     * @param plan_reader $reader Used to flatten each week's/activity's raw Custom Field data.
     * @param array $weeks Result of plan_reader::weeks().
     * @param bool $showtutorfields Whether to include the tutor-exclusive fields.
     * @return stdClass[]
     */
    public static function weeks(plan_reader $reader, array $weeks, bool $showtutorfields): array {
        $result = [];
        foreach ($weeks as $week) {
            $fields = $reader->flatten_fields($week->fields);
            $data = [
                'id'                    => $week->id,
                'title'                 => $week->title,
                'duration'              => $week->duration,
                'startdate'             => $week->startdate,
                'enddate'               => $week->enddate,
                'syncdate'              => $week->syncdate,
                'synclink'              => $week->synclink,
                'synctopic'             => $week->synctopic,
                'hassync'               => !empty($week->syncdate),
                'details'               => $fields['details'] ?? '',
                'supportmaterial'       => $fields['supportmaterial'] ?? '',
                'supplementarymaterial' => $fields['supplementarymaterial'] ?? '',
                'showtutorfields'       => $showtutorfields,
                'activities'            => self::activities($reader, $week->activities, $showtutorfields),
                'hasactivities'         => !empty($week->activities),
            ];
            if ($showtutorfields) {
                $data['interactiontools'] = $fields['interactiontools'] ?? '';
                $data['notes'] = $fields['notes'] ?? '';
            }
            $result[] = (object) $data;
        }
        return $result;
    }

    /**
     * Exports the plan-level Final assessment block for the read-only views — a single object,
     * never a per-week list: the source document treats it as its own standalone block,
     * structurally paralleling Characterisation, not an activity inside any particular week.
     *
     * @param stdClass $syllabus The syllabus record.
     * @param stdClass $narrative Result of plan_reader::plan_narrative().
     * @return stdClass
     */
    public static function final_assessment(stdClass $syllabus, stdClass $narrative): stdClass {
        return (object) [
            'title'        => $syllabus->finalassessmenttitle,
            'type'         => $syllabus->finalassessmenttype,
            'startdate'    => $syllabus->finalassessmentstartdate,
            'enddate'      => $syllabus->finalassessmentenddate,
            'points'       => $syllabus->finalassessmentpoints,
            'instructions' => $narrative->finalassessmentinstructions ?? '',
        ];
    }

    /**
     * Exports a read-only view of one week's activities.
     *
     * @param plan_reader $reader Used to flatten each activity's raw Custom Field data.
     * @param array $activities syllabus_activities rows, each carrying ->fields (raw data_controllers).
     * @param bool $showtutorfields Whether to include the tutor-exclusive fields.
     * @return stdClass[]
     */
    private static function activities(plan_reader $reader, array $activities, bool $showtutorfields): array {
        $result = [];
        foreach ($activities as $activity) {
            $fields = $reader->flatten_fields($activity->fields);
            $data = [
                'id'                  => $activity->id,
                'title'               => $activity->title,
                'type'                => $activity->type,
                'category'            => $activity->category,
                'startdate'           => $activity->startdate,
                'enddate'             => $activity->enddate,
                'points'              => $activity->points,
                'studentinstructions' => $fields['studentinstructions'] ?? '',
                'showtutorfields'     => $showtutorfields,
            ];
            if ($showtutorfields) {
                $data['gradingcriteria'] = $fields['gradingcriteria'] ?? '';
                $data['tutorguidance'] = $fields['tutorguidance'] ?? '';
            }
            $result[] = (object) $data;
        }
        return $result;
    }
}
