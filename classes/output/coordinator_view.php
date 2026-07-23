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

use context_module;
use core_customfield\data_controller;
use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;
use mod_syllabus\local\plan_state_manager;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Full plan view shown to the teacher (author) and to the coordination (reviewer).
 *
 * The author (mod/syllabus:submit) gets the editable weeks/activities management UI, with
 * narrative Custom Field content wired for autosave; a reviewer without that capability sees
 * the same read-only content Phase 2 already rendered. Submit/review actions themselves are
 * wired in a later phase once the workflow events exist.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coordinator_view implements renderable, templatable {
    /** @var stdClass The syllabus record. */
    private stdClass $syllabus;

    /** @var stdClass The course module record. */
    private stdClass $cm;

    /**
     * Creates the coordinator/teacher view for a given syllabus instance.
     *
     * @param stdClass $syllabus The syllabus record.
     * @param stdClass $cm The course module record.
     */
    public function __construct(stdClass $syllabus, stdClass $cm) {
        $this->syllabus = $syllabus;
        $this->cm = $cm;
    }

    /**
     * Exports the plan's content, status, and (for the author) the editable weeks/activities.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        $context = context_module::instance($this->cm->id);
        $caneditcontent = has_capability('mod/syllabus:submit', $context);
        $canreview = has_capability('mod/syllabus:review', $context);
        $status = $this->syllabus->status;
        $isauthor = (int) $this->syllabus->submittedby === (int) $USER->id;

        $planhandler = plan_handler::create();
        $content = $planhandler->export_instance_data_object($this->syllabus->id);

        $data = (object) [
            'cmid'              => $this->cm->id,
            'statuslabel'       => get_string(plan_state_manager::status_string_key($status), 'mod_syllabus'),
            'statusbadgeclass'  => plan_state_manager::status_badge_class($status),
            'caneditcontent'    => $caneditcontent,
            'coursedescription' => $content->coursedescription ?? '',
            'objectives'        => $content->objectives ?? '',
            'contents'          => $content->contents ?? '',
            'methodology'       => $content->methodology ?? '',
            'cansubmit'         => $caneditcontent && in_array($status, [
                plan_state_manager::STATUS_DRAFT,
                plan_state_manager::STATUS_CHANGES_REQUESTED,
            ], true),
            'canreviewnow'      => $canreview && $status === plan_state_manager::STATUS_SUBMITTED,
            'canunpublish'      => $status === plan_state_manager::STATUS_APPROVED && ($canreview || $isauthor),
            'changesrequestedreason' => $status === plan_state_manager::STATUS_CHANGES_REQUESTED
                ? $this->syllabus->changesrequestedreason
                : null,
        ];

        if ($caneditcontent) {
            $planfields = $planhandler->get_instance_data($this->syllabus->id, true);
            $data->planfields = $this->export_editable_fields($planfields);
            $data->weeks = $this->export_weeks();
            $data->hasweeks = !empty($data->weeks);
        }

        return $data;
    }

    /**
     * Exports every week (with its narrative fields) and its activities (with theirs),
     * batching the Custom Field value lookup across all weeks/activities at once rather than
     * querying per row.
     *
     * @return array
     */
    private function export_weeks(): array {
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
                $weekactivities[] = (object) [
                    'id'        => $activity->id,
                    'weekid'    => $week->id,
                    'title'     => $activity->title,
                    'type'      => $activity->type,
                    'category'  => $activity->category,
                    'startdate' => $activity->startdate,
                    'enddate'   => $activity->enddate,
                    'points'    => $activity->points,
                    'fields'    => $this->export_editable_fields($activityfielddata[$activity->id] ?? []),
                ];
            }
            $result[] = (object) [
                'id'            => $week->id,
                'title'         => $week->title,
                'duration'      => $week->duration,
                'startdate'     => $week->startdate,
                'enddate'       => $week->enddate,
                'fields'        => $this->export_editable_fields($weekfielddata[$week->id] ?? []),
                'activities'    => $weekactivities,
                'hasactivities' => !empty($weekactivities),
            ];
        }
        return $result;
    }

    /**
     * Prepares a fresh draft file area per textarea Custom Field so the template can render an
     * editable box for it, pre-filled with the field's current value — the same preparation an
     * mform's editor element does internally (instance_form_before_set_data()), reused here
     * outside of one, matching how save_customfield_value::execute() saves it back.
     *
     * Deliberately renders as a plain HTML source textarea, not a live TinyMCE instance: wiring
     * a standalone Tiny editor with a working image/file picker outside an mform means
     * replicating ~70 lines of filepicker option construction that MoodleQuickForm_editor keeps
     * private (lib/form/editor.php) — real but fragile duplicate code, not a stable public API.
     * A future iteration should render each area's fields through a minimal, submit-less
     * moodleform built from handler::instance_form_definition() instead, purely to get Tiny's
     * mform-only wiring for free; the itemid still saved here already supports that migration
     * without a save_customfield_value contract change.
     *
     * @param data_controller[] $datacontrollers Field id => data_controller, as returned by
     *                                            handler::get_instance_data()/get_instances_data().
     * @return array
     */
    private function export_editable_fields(array $datacontrollers): array {
        $result = [];
        foreach ($datacontrollers as $fieldid => $datacontroller) {
            if ($datacontroller->get_field()->get('type') !== 'textarea') {
                // Only textarea fields are editable through this UI — see save_customfield_value.
                continue;
            }
            $holder = new stdClass();
            $datacontroller->instance_form_before_set_data($holder);
            $editorvalue = $holder->{$datacontroller->get_form_element_name()};
            $result[] = (object) [
                'fieldid'    => $fieldid,
                'instanceid' => $datacontroller->get('instanceid'),
                'elementid'  => 'syllabus-field-' . $datacontroller->get('instanceid') . '-' . $fieldid,
                'name'       => $datacontroller->get_field()->get_formatted_name(),
                'text'       => $editorvalue['text'],
                'itemid'     => $editorvalue['itemid'],
            ];
        }
        return $result;
    }
}
