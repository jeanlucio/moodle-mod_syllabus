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
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\local\plan_state_manager;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Tab 1 — "Plano completo": the teacher (author) gets the editable weeks/activities
 * management UI with narrative Custom Field autosave; coordination/admin without
 * mod/syllabus:submit gets the same content coordination review, plus the review panel and
 * workflow actions. Both branches see the full field set — this tab's visibility is nothing
 * more/less than "everything the plan has" (see the matrix in SCOPE §8); the split is
 * edit-vs-read, not field visibility.
 *
 * The visual editing form built here is the same simple pattern the plugin already used
 * before Fase 5.5 — this class' job is making sure every field the documents require has
 * *some* way to be edited and read correctly, with the right people seeing the right things.
 * Fase 5.5.d.1 added the closed-select option lists (build_select_options()) and per-field
 * help text (via plan_reader::export_editable_fields()); the navigation rail, totals bar and
 * real date pickers remain 5.5.d.2/5.5.d.3's job.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tab_full_plan implements renderable, templatable {
    /**
     * Fixed set of activity types the source documents define, token => lang string key.
     * The `type` column stays free VARCHAR — this only drives the closed `<select>` in the
     * edit-mode UI (Fase 5.5.d.1); save_activity still accepts any string, so a legacy value
     * outside this list is never lost (see build_select_options()).
     */
    private const TYPE_OPTIONS = [
        'forum'         => 'typeforum',
        'questionnaire' => 'typequestionnaire',
        'task'          => 'typetask',
        'quiz'          => 'typequiz',
        'game'          => 'typegame',
        'chat'          => 'typechat',
        'syncmeeting'   => 'syncmeeting',
        'other'         => 'typeother',
    ];

    /**
     * Fixed set of activity categories the source documents define, token => lang string key.
     * Same free-VARCHAR/closed-select relationship as TYPE_OPTIONS above.
     */
    private const CATEGORY_OPTIONS = [
        'synchronous'  => 'categorysynchronous',
        'asynchronous' => 'categoryasynchronous',
        'online'       => 'categoryonline',
    ];

    /** @var stdClass The syllabus record. */
    private stdClass $syllabus;

    /** @var stdClass The course module record. */
    private stdClass $cm;

    /** @var stdClass The course record. */
    private stdClass $course;

    /**
     * Creates the full plan tab for a given syllabus instance.
     *
     * @param stdClass $syllabus The syllabus record.
     * @param stdClass $cm The course module record.
     * @param stdClass $course The course record.
     */
    public function __construct(stdClass $syllabus, stdClass $cm, stdClass $course) {
        $this->syllabus = $syllabus;
        $this->cm = $cm;
        $this->course = $course;
    }

    /**
     * Exports either the editable form data (author) or the full read-only content plus the
     * review panel flags (coordination/admin).
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

        $reader = new plan_reader($this->syllabus);
        $narrative = $reader->plan_narrative();
        $weeks = $reader->weeks();

        $data = (object) [
            'cmid'              => $this->cm->id,
            'statuslabel'       => get_string(plan_state_manager::status_string_key($status), 'mod_syllabus'),
            'statusbadgeclass'  => plan_state_manager::status_badge_class($status),
            'caneditcontent'    => $caneditcontent,
            'academicperiod'    => $this->syllabus->academicperiod,
            'coursestartdate'   => $this->syllabus->coursestartdate,
            'courseenddate'     => $this->syllabus->courseenddate,
            'coursestartdatefield' => $this->date_select_field(
                'syllabus-plan-coursestartdate',
                'syllabus-plan-coursestartdate',
                'coursestartdate',
                $this->syllabus->coursestartdate
            ),
            'courseenddatefield' => $this->date_select_field(
                'syllabus-plan-courseenddate',
                'syllabus-plan-courseenddate',
                'courseenddate',
                $this->syllabus->courseenddate
            ),
            'totalduration'     => $this->syllabus->totalduration,
            'presentationvideourl' => $this->syllabus->presentationvideourl,
            'coursedescription' => $narrative->coursedescription ?? '',
            'objectives'        => $narrative->objectives ?? '',
            'contents'          => $narrative->contents ?? '',
            'methodology'       => $narrative->methodology ?? '',
            'presentationscript' => $narrative->presentationscript ?? '',
            'generalreferences'  => $narrative->generalreferences ?? '',
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
            $planfielddata = plan_handler::create()->get_instance_data($this->syllabus->id, true);
            $data->planfields = $reader->export_editable_fields($planfielddata, 'plan');
            $data->weeks = $this->export_editable_weeks($reader, $weeks);
            $data->hasweeks = !empty($data->weeks);
            $data->tinyavailable = narrative_editor::is_tiny_available();
            if ($data->tinyavailable) {
                $data->tinyconfig = json_encode(narrative_editor::base_config($context));
            }
        } else {
            $data->readweeks = plan_read_export::weeks($reader, $weeks, true);
            $data->hasweeks = !empty($data->readweeks);
            $finalassessments = plan_read_export::final_assessment_activities($data->readweeks);
            $data->finalassessments = $finalassessments;
            $data->hasfinalassessments = !empty($finalassessments);
            $schedule = $reader->schedule($weeks);
            $data->schedule = $schedule;
            $data->hasschedule = !empty($schedule);
        }

        return $data;
    }

    /**
     * Reshapes plan_reader::weeks() into the editable-form shape week_row.mustache expects:
     * structural fields as plain values, narrative fields as editable boxes.
     *
     * @param plan_reader $reader Used to build each field's editable-box representation.
     * @param array $weeks Result of plan_reader::weeks().
     * @return array
     */
    private function export_editable_weeks(plan_reader $reader, array $weeks): array {
        $result = [];
        foreach ($weeks as $week) {
            $weekactivities = [];
            foreach ($week->activities as $activity) {
                $weekactivities[] = (object) [
                    'id'                => $activity->id,
                    'weekid'            => $week->id,
                    'title'             => $activity->title,
                    'type'              => $activity->type,
                    'category'          => $activity->category,
                    'startdate'         => $activity->startdate,
                    'enddate'           => $activity->enddate,
                    'startdatefield'    => $this->date_select_field(
                        'syllabus-activity-startdate-' . $activity->id,
                        'syllabus-activity-startdate',
                        'activitystartdate',
                        $activity->startdate
                    ),
                    'enddatefield'      => $this->date_select_field(
                        'syllabus-activity-enddate-' . $activity->id,
                        'syllabus-activity-enddate',
                        'activityenddate',
                        $activity->enddate
                    ),
                    'points'            => $activity->points,
                    'isfinalassessment' => (bool) $activity->isfinalassessment,
                    'typeoptions'       => $this->build_select_options(self::TYPE_OPTIONS, $activity->type),
                    'categoryoptions'   => $this->build_select_options(self::CATEGORY_OPTIONS, $activity->category),
                    'fields'            => $reader->export_editable_fields($activity->fields, 'activity'),
                ];
            }
            $result[] = (object) [
                'id'            => $week->id,
                'title'         => $week->title,
                'duration'      => $week->duration,
                'startdate'     => $week->startdate,
                'enddate'       => $week->enddate,
                'syncdate'      => $week->syncdate,
                'synclink'      => $week->synclink,
                'synctopic'     => $week->synctopic,
                'fields'        => $reader->export_editable_fields($week->fields, 'week'),
                'activities'    => $weekactivities,
                'hasactivities' => !empty($weekactivities),
            ];
        }
        return $result;
    }

    /**
     * Builds the option list for a closed `<select>` (activity type/category), marking the
     * currently stored value as selected. If that value doesn't match any of the fixed
     * tokens (legacy data, or an institution that typed something else before this UI
     * existed), it is appended as an extra selected option instead of being silently
     * dropped — the column is still free VARCHAR, so no data is lost either way.
     *
     * @param array $tokenstolangkeys Fixed value => lang string key map (TYPE_OPTIONS/CATEGORY_OPTIONS).
     * @param string|null $current The value currently stored on the activity.
     * @return stdClass[]
     */
    private function build_select_options(array $tokenstolangkeys, ?string $current): array {
        $options = [];
        $matched = false;
        foreach ($tokenstolangkeys as $value => $langkey) {
            $selected = $current !== null && $current === $value;
            $matched = $matched || $selected;
            $options[] = (object) [
                'value'    => $value,
                'label'    => get_string($langkey, 'mod_syllabus'),
                'selected' => $selected,
            ];
        }
        if (!$matched && $current !== null && $current !== '') {
            $options[] = (object) [
                'value'    => $current,
                'label'    => $current,
                'selected' => true,
            ];
        }
        return $options;
    }

    /**
     * Builds the context object date_select.mustache expects — always used scoped to its own
     * Mustache section (e.g. `{{#startdatefield}}{{> mod_syllabus/date_select}}{{/startdatefield}}`)
     * so several date fields on the same page never collide on variable names.
     *
     * @param string $fieldid Element id for this specific field instance, unique on the page.
     * @param string $fieldclass Base CSS class identifying this field's 3 selects.
     * @param string $labelkey Lang string key (component mod_syllabus) for the field's label.
     * @param int|null $timestamp Currently stored value, or null/0 if unset.
     * @return stdClass
     */
    private function date_select_field(string $fieldid, string $fieldclass, string $labelkey, ?int $timestamp): stdClass {
        return (object) [
            'fieldid'    => $fieldid,
            'fieldclass' => $fieldclass,
            'labeltext'  => get_string($labelkey, 'mod_syllabus'),
            'timestamp'  => $timestamp,
        ];
    }
}
