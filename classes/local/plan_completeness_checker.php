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

use core_customfield\data_controller;
use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;
use stdClass;

/**
 * Lists the required content still missing from a plan, before it can be submitted for review.
 *
 * Two independent kinds of "required" feed into this. Narrative content (core_customfield
 * textarea fields under the plan/week/activity areas) already carries its own admin-configured
 * "Is this field required?" flag, read directly off each field_controller via
 * get_configdata_property('required') — nothing new to store for those. Structural columns on
 * {syllabus}/{syllabus_weeks}/{syllabus_activities} have no such flag of their own (they are
 * fixed schema, not admin-configurable custom fields), so their requiredness is instead a
 * handful of admin_setting_configcheckbox toggles in settings.php, read here via get_config().
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_completeness_checker {
    /**
     * Every required field left empty in the given plan, as human-readable labels (already
     * prefixed with the week/activity they belong to, where applicable) ready to join into a
     * single error message. An empty array means the plan is complete enough to submit.
     *
     * @param stdClass $plan The syllabus record.
     * @return string[]
     */
    public static function missing_required_fields(stdClass $plan): array {
        global $DB;

        $missing = self::missing_characterisation_fields($plan);
        $missing = array_merge($missing, self::missing_narrative_fields(
            plan_handler::create()->get_instance_data($plan->id, true)
        ));

        $weeks = $DB->get_records('syllabus_weeks', ['syllabusid' => $plan->id], 'sortorder ASC');
        if (!$weeks) {
            return $missing;
        }

        $weekids = array_keys($weeks);
        $activities = $DB->get_records_list('syllabus_activities', 'weekid', $weekids, 'sortorder ASC');
        $activitiesbyweek = [];
        foreach ($activities as $activity) {
            $activitiesbyweek[$activity->weekid][] = $activity;
        }

        $weekfielddata = week_handler::create()->get_instances_data($weekids, true);
        $activityfielddata = $activities
            ? activity_handler::create()->get_instances_data(array_keys($activities), true)
            : [];

        $requireweekplanning = (bool) get_config('mod_syllabus', 'requireweekplanning');
        $requireactivitytype = (bool) get_config('mod_syllabus', 'requireactivitytype');
        $requireactivityperiod = (bool) get_config('mod_syllabus', 'requireactivityperiod');

        foreach ($weeks as $week) {
            if ($requireweekplanning && self::week_planning_incomplete($week)) {
                $missing[] = "{$week->title}: " . get_string('weekplanning', 'mod_syllabus');
            }
            foreach (self::missing_narrative_fields($weekfielddata[$week->id] ?? []) as $label) {
                $missing[] = "{$week->title}: {$label}";
            }

            foreach ($activitiesbyweek[$week->id] ?? [] as $activity) {
                $activityprefix = "{$week->title} › {$activity->title}";
                if ($requireactivitytype && trim((string) $activity->type) === '') {
                    $missing[] = "{$activityprefix}: " . get_string('activitytype', 'mod_syllabus');
                }
                if ($requireactivityperiod && (empty($activity->startdate) || empty($activity->enddate))) {
                    $missing[] = "{$activityprefix}: " . get_string('activityperiod', 'mod_syllabus');
                }
                foreach (self::missing_narrative_fields($activityfielddata[$activity->id] ?? []) as $label) {
                    $missing[] = "{$activityprefix}: {$label}";
                }
            }
        }

        return $missing;
    }

    /**
     * Checks the plan-level structural fields (Characterisation + Final assessment), each
     * gated by its own admin_setting_configcheckbox toggle.
     *
     * @param stdClass $plan The syllabus record.
     * @return string[]
     */
    private static function missing_characterisation_fields(stdClass $plan): array {
        $missing = [];

        if (get_config('mod_syllabus', 'requireacademicperiod') && trim((string) $plan->academicperiod) === '') {
            $missing[] = get_string('academicperiod', 'mod_syllabus');
        }
        $courseperiodrequired = (bool) get_config('mod_syllabus', 'requirecourseperiod');
        if ($courseperiodrequired && (empty($plan->coursestartdate) || empty($plan->courseenddate))) {
            $missing[] = get_string('courseperiod', 'mod_syllabus');
        }
        if (get_config('mod_syllabus', 'requiretotalworkload') && empty($plan->totalduration)) {
            $missing[] = get_string('totalworkload', 'mod_syllabus');
        }
        if (get_config('mod_syllabus', 'requirefinalassessment') && self::final_assessment_incomplete($plan)) {
            $missing[] = get_string('finalassessment', 'mod_syllabus');
        }

        return $missing;
    }

    /**
     * Whether any of the Final assessment block's own fields is still empty.
     *
     * @param stdClass $plan The syllabus record.
     * @return bool
     */
    private static function final_assessment_incomplete(stdClass $plan): bool {
        return trim((string) $plan->finalassessmenttitle) === ''
            || trim((string) $plan->finalassessmenttype) === ''
            || empty($plan->finalassessmentstartdate)
            || empty($plan->finalassessmentenddate)
            || $plan->finalassessmentpoints === null;
    }

    /**
     * Whether a week's own workload/period group ("weekplanning") is still incomplete.
     *
     * @param stdClass $week The syllabus_weeks record.
     * @return bool
     */
    private static function week_planning_incomplete(stdClass $week): bool {
        return empty($week->duration) || empty($week->startdate) || empty($week->enddate);
    }

    /**
     * Formatted names of every required narrative field left empty among the given
     * data_controllers, one instance's worth at a time.
     *
     * @param data_controller[] $datacontrollers Field id => data_controller, for one instance.
     * @return string[]
     */
    private static function missing_narrative_fields(array $datacontrollers): array {
        $missing = [];
        foreach ($datacontrollers as $datacontroller) {
            $field = $datacontroller->get_field();
            if (!$field->get_configdata_property('required')) {
                continue;
            }
            $value = trim(strip_tags((string) $datacontroller->get_value()));
            if ($value === '') {
                $missing[] = $field->get_formatted_name();
            }
        }
        return $missing;
    }
}
