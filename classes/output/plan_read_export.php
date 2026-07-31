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
     * @param bool $includereviewnotes Whether to attach a coordinator review-note object
     *     (fieldid/instanceid/area/coordinatornote) next to each narrative field, for inline
     *     rendering right under it — only the coordinator's own read of Tab 1 sets this,
     *     never the tutor/student tabs.
     * @param array $reviewnotes Result of plan_reader::review_notes(), for this same plan.
     * @return stdClass[]
     */
    public static function weeks(
        plan_reader $reader,
        array $weeks,
        bool $showtutorfields,
        bool $includereviewnotes = false,
        array $reviewnotes = []
    ): array {
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
                'activities'            => self::activities(
                    $reader,
                    $week->activities,
                    $showtutorfields,
                    $includereviewnotes,
                    $reviewnotes
                ),
                'hasactivities'         => !empty($week->activities),
            ];
            if ($showtutorfields) {
                $data['interactiontools'] = $fields['interactiontools'] ?? '';
                $data['notes'] = $fields['notes'] ?? '';
            }
            if ($includereviewnotes) {
                $notefields = $reader->review_note_fields($week->fields, 'week', $reviewnotes);
                $data['detailsreviewnote'] = $notefields['details'] ?? null;
                $data['supportmaterialreviewnote'] = $notefields['supportmaterial'] ?? null;
                $data['supplementarymaterialreviewnote'] = $notefields['supplementarymaterial'] ?? null;
                if ($showtutorfields) {
                    $data['interactiontoolsreviewnote'] = $notefields['interactiontools'] ?? null;
                    $data['notesreviewnote'] = $notefields['notes'] ?? null;
                }
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
     * @param bool $includereviewnotes Whether to attach a coordinator review-note object for
     *     the instructions field — only the coordinator's own read of Tab 1 sets this.
     * @param plan_reader|null $reader Required when $includereviewnotes is true.
     * @param array $finalassessmentfielddata Field id => data_controller for the
     *     finalassessmentinstructions field, required when $includereviewnotes is true.
     * @param array $reviewnotes Result of plan_reader::review_notes(), for this same plan.
     * @return stdClass
     */
    public static function final_assessment(
        stdClass $syllabus,
        stdClass $narrative,
        bool $includereviewnotes = false,
        ?plan_reader $reader = null,
        array $finalassessmentfielddata = [],
        array $reviewnotes = []
    ): stdClass {
        $data = [
            'title'        => $syllabus->finalassessmenttitle,
            'type'         => $syllabus->finalassessmenttype,
            'startdate'    => $syllabus->finalassessmentstartdate,
            'enddate'      => $syllabus->finalassessmentenddate,
            'points'       => $syllabus->finalassessmentpoints,
            'instructions' => $narrative->finalassessmentinstructions ?? '',
        ];
        if ($includereviewnotes && $reader !== null) {
            $notefields = $reader->review_note_fields($finalassessmentfielddata, 'plan', $reviewnotes);
            $data['instructionsreviewnote'] = $notefields['finalassessmentinstructions'] ?? null;
        }
        return (object) $data;
    }

    /**
     * Exports a read-only view of one week's activities.
     *
     * @param plan_reader $reader Used to flatten each activity's raw Custom Field data.
     * @param array $activities syllabus_activities rows, each carrying ->fields (raw data_controllers).
     * @param bool $showtutorfields Whether to include the tutor-exclusive fields.
     * @param bool $includereviewnotes Whether to attach a coordinator review-note object next
     *     to each narrative field — see weeks() for the full rationale.
     * @param array $reviewnotes Result of plan_reader::review_notes(), for this same plan.
     * @return stdClass[]
     */
    private static function activities(
        plan_reader $reader,
        array $activities,
        bool $showtutorfields,
        bool $includereviewnotes,
        array $reviewnotes
    ): array {
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
            if ($includereviewnotes) {
                $notefields = $reader->review_note_fields($activity->fields, 'activity', $reviewnotes);
                $data['studentinstructionsreviewnote'] = $notefields['studentinstructions'] ?? null;
                if ($showtutorfields) {
                    $data['gradingcriteriareviewnote'] = $notefields['gradingcriteria'] ?? null;
                    $data['tutorguidancereviewnote'] = $notefields['tutorguidance'] ?? null;
                }
            }
            $result[] = (object) $data;
        }
        return $result;
    }
}
