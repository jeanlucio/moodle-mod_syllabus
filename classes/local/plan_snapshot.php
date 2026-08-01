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

namespace mod_syllabus\local;

use mod_syllabus\output\plan_reader;
use stdClass;

/**
 * Builds a point-in-time snapshot of a plan's fields (structural columns and narrative Custom
 * Field content alike, plan/week/activity levels) and diffs two such snapshots against each
 * other — the basis for showing the coordinator exactly what changed since their last decision,
 * instead of them having to reopen the whole plan and compare from memory.
 *
 * build() never queries the database itself: it takes the same plan/narrative/weeks data
 * tab_full_plan.php and plan_state_manager already fetch for their own purposes (via
 * plan_reader), so computing a snapshot never costs an extra query beyond what the caller was
 * already paying for.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_snapshot {
    /** @var string[] Plan-level structural columns tracked for the diff. */
    private const PLAN_FIELDS = [
        'academicperiod',
        'coursestartdate',
        'courseenddate',
        'totalduration',
        'presentationvideourl',
        'stagecount',
        'grademethod',
        'finalassessmenttitle',
        'finalassessmenttype',
        'finalassessmentstartdate',
        'finalassessmentenddate',
        'finalassessmentpoints',
    ];

    /**
     * Week-level structural columns tracked for the diff — same field list save_week.php
     * compares via structural_change_detector.
     *
     * @var string[]
     */
    private const WEEK_FIELDS = ['title', 'duration', 'startdate', 'enddate', 'syncdate', 'synclink', 'synctopic', 'stage'];

    /**
     * Activity-level structural columns tracked for the diff — same field list save_activity.php
     * compares via structural_change_detector.
     *
     * @var string[]
     */
    private const ACTIVITY_FIELDS = ['title', 'type', 'category', 'startdate', 'enddate', 'points'];

    /**
     * Builds the current snapshot of a plan.
     *
     * @param stdClass $plan The syllabus record.
     * @param stdClass $narrative Result of plan_reader::plan_narrative() for this same plan.
     * @param array $weeks Result of plan_reader::weeks() for this same plan.
     * @param plan_reader $reader Used to flatten each week's/activity's raw Custom Field data.
     * @return array Nested shape: 'plan' => field values; 'weeks' => weekid => field values +
     *     'activities' => activityid => field values.
     */
    public static function build(stdClass $plan, stdClass $narrative, array $weeks, plan_reader $reader): array {
        $result = [
            'plan'  => self::extract($plan, self::PLAN_FIELDS) + (array) $narrative,
            'weeks' => [],
        ];

        foreach ($weeks as $week) {
            $weeksnapshot = self::extract($week, self::WEEK_FIELDS) + $reader->flatten_fields($week->fields);
            $weeksnapshot['activities'] = [];
            foreach ($week->activities as $activity) {
                $weeksnapshot['activities'][$activity->id] =
                    self::extract($activity, self::ACTIVITY_FIELDS) + $reader->flatten_fields($activity->fields);
            }
            $result['weeks'][$week->id] = $weeksnapshot;
        }

        return $result;
    }

    /**
     * Compares two snapshots built by build(), reporting what changed between them.
     *
     * @param array $old Snapshot from the coordinator's last decision (e.g. json_decode of
     *     syllabus.reviewsnapshot).
     * @param array $new Current snapshot, from build().
     * @return array 'planfields' => changed plan-level field names; 'weeks' => weekid =>
     *     changed field names (only weeks present in both); 'activities' => activityid =>
     *     changed field names (only activities present in both); 'newweekids'/'newactivityids'
     *     => ids present in $new but not $old; 'removedweektitles'/'removedactivitylabels' =>
     *     human-readable labels for rows present in $old but not $new (there is no live row
     *     left to attach an id-based indicator to).
     */
    public static function diff(array $old, array $new): array {
        $result = [
            'planfields'            => self::changed_keys($old['plan'] ?? [], $new['plan'] ?? []),
            'weeks'                 => [],
            'activities'            => [],
            'newweekids'            => [],
            'removedweektitles'     => [],
            'newactivityids'        => [],
            'removedactivitylabels' => [],
        ];

        $oldweeks = $old['weeks'] ?? [];
        $newweeks = $new['weeks'] ?? [];

        foreach ($newweeks as $weekid => $weeksnapshot) {
            if (!array_key_exists($weekid, $oldweeks)) {
                $result['newweekids'][] = (int) $weekid;
                continue;
            }

            $oldweek = $oldweeks[$weekid];
            $changed = self::changed_keys(self::without_activities($oldweek), self::without_activities($weeksnapshot));
            if ($changed) {
                $result['weeks'][(int) $weekid] = $changed;
            }

            $oldactivities = $oldweek['activities'] ?? [];
            $newactivities = $weeksnapshot['activities'] ?? [];
            foreach ($newactivities as $activityid => $activitysnapshot) {
                if (!array_key_exists($activityid, $oldactivities)) {
                    $result['newactivityids'][] = (int) $activityid;
                    continue;
                }
                $activitychanged = self::changed_keys($oldactivities[$activityid], $activitysnapshot);
                if ($activitychanged) {
                    $result['activities'][(int) $activityid] = $activitychanged;
                }
            }
            foreach ($oldactivities as $activityid => $oldactivity) {
                if (!array_key_exists($activityid, $newactivities)) {
                    $result['removedactivitylabels'][] =
                        ($oldweek['title'] ?? '?') . ' › ' . ($oldactivity['title'] ?? '?');
                }
            }
        }

        foreach ($oldweeks as $weekid => $oldweek) {
            if (!array_key_exists($weekid, $newweeks)) {
                $result['removedweektitles'][] = $oldweek['title'] ?? '?';
            }
        }

        return $result;
    }

    /**
     * Pulls a fixed set of column values off a record into a plain array.
     *
     * @param stdClass $record The syllabus/syllabus_weeks/syllabus_activities record.
     * @param string[] $fields Column names to extract.
     * @return array
     */
    private static function extract(stdClass $record, array $fields): array {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $record->$field ?? null;
        }
        return $result;
    }

    /**
     * A week snapshot with its nested 'activities' key removed, so comparing a week's own
     * fields never treats an unrelated activity-level change as a week-level one.
     *
     * @param array $weeksnapshot One week's entry from build()'s 'weeks' array.
     * @return array
     */
    private static function without_activities(array $weeksnapshot): array {
        unset($weeksnapshot['activities']);
        return $weeksnapshot;
    }

    /**
     * Field names whose value differs between two flat field-value arrays, comparing as
     * strings — same philosophy as structural_change_detector::changed(), just reporting which
     * keys differ instead of a single bool.
     *
     * @param array $old shortname/column => value.
     * @param array $new shortname/column => value.
     * @return string[]
     */
    private static function changed_keys(array $old, array $new): array {
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $changed = [];
        foreach ($keys as $key) {
            if ((string) ($old[$key] ?? '') !== (string) ($new[$key] ?? '')) {
                $changed[] = $key;
            }
        }
        return $changed;
    }
}
