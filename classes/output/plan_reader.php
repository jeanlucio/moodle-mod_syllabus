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

use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;
use stdClass;

/**
 * Fetches everything about one syllabus plan in a handful of batched queries, so the three
 * tab renderables (full plan, student plan, tutor plan) never each run their own N+1 pass
 * over the same weeks/activities/Custom Field data.
 *
 * `weeks()` returns the raw Custom Field data_controllers per week/activity, not a
 * pre-formatted view — each renderable decides whether it needs an editable representation
 * (export_editable_fields(), for the author's form) or a flattened read-only one
 * (flatten_fields()), and which of the resulting shortnames it actually copies into its own
 * export_for_template() array. This class never filters by role: the per-tab visibility
 * matrix is enforced entirely by what each renderable chooses to export — a field never
 * exported is a field that never reaches the template.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_reader {
    /** @var stdClass The syllabus record. */
    private stdClass $syllabus;

    /**
     * Creates a reader for a given syllabus instance.
     *
     * @param stdClass $syllabus The syllabus record.
     */
    public function __construct(stdClass $syllabus) {
        $this->syllabus = $syllabus;
    }

    /**
     * Plan-level narrative Custom Field values (coursedescription, objectives, etc.), unfiltered.
     *
     * @return stdClass
     */
    public function plan_narrative(): stdClass {
        return plan_handler::create()->export_instance_data_object($this->syllabus->id, true);
    }

    /**
     * Every week of the plan, each carrying its raw Custom Field data_controllers (under
     * ->fields) and its activities (each in turn carrying theirs), fetched via three batched
     * queries total regardless of how many weeks/activities exist.
     *
     * @return array
     */
    public function weeks(): array {
        global $DB;

        $weeks = $DB->get_records('syllabus_weeks', ['syllabusid' => $this->syllabus->id], 'sortorder ASC');
        if (!$weeks) {
            return [];
        }

        $activitiesbyweek = [];
        $activities = $DB->get_records_list('syllabus_activities', 'weekid', array_keys($weeks), 'sortorder ASC');
        foreach ($activities as $activity) {
            $activitiesbyweek[$activity->weekid][] = $activity;
        }

        $weekfielddata = week_handler::create()->get_instances_data(array_keys($weeks), true);
        $activityfielddata = $activities
            ? activity_handler::create()->get_instances_data(array_keys($activities), true)
            : [];

        $result = [];
        foreach ($weeks as $week) {
            $weekactivities = [];
            foreach ($activitiesbyweek[$week->id] ?? [] as $activity) {
                $activityrecord = clone $activity;
                $activityrecord->fields = $activityfielddata[$activity->id] ?? [];
                $weekactivities[] = $activityrecord;
            }
            $weekrecord = clone $week;
            $weekrecord->fields = $weekfielddata[$week->id] ?? [];
            $weekrecord->activities = $weekactivities;
            $result[] = $weekrecord;
        }
        return $result;
    }

    /**
     * The consolidated schedule: every activity across every week, flattened and sorted by
     * end date, matching the "Activity and grading schedule" table in all 3 source
     * documents. Never persisted — computed fresh from weeks() each time it's needed.
     *
     * @param array $weeks Result of weeks(), passed in to avoid querying twice in the same request.
     * @return array
     */
    public function schedule(array $weeks): array {
        $rows = [];
        foreach ($weeks as $week) {
            foreach ($week->activities as $activity) {
                $rows[] = (object) [
                    'weektitle' => $week->title,
                    'type'      => $activity->type,
                    'category'  => $activity->category,
                    'title'     => $activity->title,
                    'points'    => $activity->points,
                    'enddate'   => $activity->enddate,
                ];
            }
        }
        usort($rows, fn ($a, $b) => ($a->enddate ?? 0) <=> ($b->enddate ?? 0));
        return $rows;
    }

    /**
     * Prepares a fresh draft file area per textarea Custom Field so an edit-mode template can
     * render an editable box for it, pre-filled with the field's current value — the same
     * preparation an mform's editor element does internally
     * (instance_form_before_set_data()), reused here outside of one, matching how
     * save_customfield_value::execute() saves it back.
     *
     * @param \core_customfield\data_controller[] $datacontrollers Field id => data_controller.
     * @param string $area Which area these fields belong to (plan/week/activity) — carried
     *     through as data-area so the autosave WS knows which handler to save back through.
     * @return array
     */
    public function export_editable_fields(array $datacontrollers, string $area): array {
        $result = [];
        foreach ($datacontrollers as $fieldid => $datacontroller) {
            if ($datacontroller->get_field()->get('type') !== 'textarea') {
                // Only textarea fields are editable through this UI — see save_customfield_value.
                continue;
            }
            $holder = new stdClass();
            $datacontroller->instance_form_before_set_data($holder);
            $editorvalue = $holder->{$datacontroller->get_form_element_name()};
            $field = $datacontroller->get_field();
            $description = (string) $field->get('description');
            $result[] = (object) [
                'fieldid'     => $fieldid,
                'instanceid'  => $datacontroller->get('instanceid'),
                'elementid'   => 'syllabus-field-' . $datacontroller->get('instanceid') . '-' . $fieldid,
                'name'        => $field->get_formatted_name(),
                'text'        => $editorvalue['text'],
                'itemid'      => $editorvalue['itemid'],
                'area'        => $area,
                // Only plan-level fields count toward the Characterisation rail section's
                // fill heuristic — a week/activity narrative field lives inside the WEEK's
                // own data-syllabus-section wrapper, so marking it required-input too would
                // dilute that section's state with fields the rail was never meant to track.
                'isplanfield' => $area === 'plan',
                'description' => $description === '' ? '' : format_text($description, (int) $field->get('descriptionformat'), [
                    'context' => $field->get_handler()->get_configuration_context(),
                ]),
            ];
        }
        return $result;
    }

    /**
     * Flattens a set of data_controllers into shortname => formatted value, for read-only
     * templates that access a field directly by name (e.g. `{{details}}`).
     *
     * @param \core_customfield\data_controller[] $datacontrollers Field id => data_controller.
     * @return array shortname => value
     */
    public function flatten_fields(array $datacontrollers): array {
        $result = [];
        foreach ($datacontrollers as $datacontroller) {
            $shortname = $datacontroller->get_field()->get('shortname');
            $result[$shortname] = $datacontroller->get_value();
        }
        return $result;
    }
}
