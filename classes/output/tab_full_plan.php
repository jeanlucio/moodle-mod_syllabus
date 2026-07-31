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
 * Tab 1 — "Full plan": the teacher (author) gets the editable weeks/activities
 * management UI with narrative Custom Field autosave; coordination/admin without
 * mod/syllabus:submit gets the same content for review, plus the review panel and workflow
 * actions. Both branches see the full field set — this tab's visibility is nothing more/less
 * than "everything the plan has"; the split is edit-vs-read, not field visibility.
 *
 * This class' job is making sure every field the documents require has *some* way to be
 * edited and read correctly, with the right people seeing the right things — including the
 * closed-select option lists (build_select_options()) and per-field help text (via
 * plan_reader::export_editable_fields()). The navigation rail, totals bar and real date
 * pickers live elsewhere.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tab_full_plan implements renderable, templatable {
    /**
     * Fixed set of activity types, token => lang string key.
     * The `type` column stays free VARCHAR — this only drives the closed `<select>` in the
     * edit-mode UI; save_activity still accepts any string, so a value outside this list is
     * never lost (see build_select_options()).
     */
    private const TYPE_OPTIONS = [
        'forum'         => 'typeforum',
        'questionnaire' => 'typequestionnaire',
        'task'          => 'typetask',
        'quiz'          => 'typequiz',
        'game'          => 'typegame',
        'chat'          => 'typechat',
        'syncmeeting'   => 'syncmeeting',
        'presential'    => 'typepresential',
        'other'         => 'typeother',
    ];

    /**
     * Fixed set of activity categories, token => lang string key.
     * Same free-VARCHAR/closed-select relationship as TYPE_OPTIONS above.
     */
    private const CATEGORY_OPTIONS = [
        'synchronous'  => 'categorysynchronous',
        'asynchronous' => 'categoryasynchronous',
        'online'       => 'categoryonline',
    ];

    /**
     * Fixed set of grading methods, token => lang string key. Purely informational — this
     * plan never computes an actual grade from either method.
     */
    private const GRADEMETHOD_OPTIONS = [
        'sum'     => 'grademethodsum',
        'average' => 'grademethodaverage',
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
        $reviewnotes = $reader->review_notes();

        // A brand-new plan (neither date ever set) is prefilled with today as a start and a
        // month later as an end, mirroring Moodle's own date_selector convention of defaulting
        // to "now" — but only visually, until the JS side actually autosaves it (see
        // date_select.mustache's autosavedefault flag). Never overrides a plan that already has
        // one of the two dates set — that is a deliberate choice by whoever left the other one
        // blank, not an empty plan waiting for defaults.
        $coursestartdate = $this->syllabus->coursestartdate;
        $courseenddate = $this->syllabus->courseenddate;
        $datesweredefaulted = false;
        if ($caneditcontent && empty($coursestartdate) && empty($courseenddate)) {
            $coursestartdate = usergetmidnight(time());
            $courseenddate = strtotime('+1 month', $coursestartdate);
            $datesweredefaulted = true;
        }

        $stagecount = max(1, (int) $this->syllabus->stagecount);

        $data = (object) [
            'cmid'              => $this->cm->id,
            'statuslabel'       => get_string(plan_state_manager::status_string_key($status), 'mod_syllabus'),
            'statusbadgeclass'  => plan_state_manager::status_badge_class($status),
            'caneditcontent'    => $caneditcontent,
            'canreview'         => $canreview,
            'stagecount'        => $stagecount,
            'hasmultiplestages' => $stagecount > 1,
            'grademethodlabel'  => get_string('grademethod' . $this->syllabus->grademethod, 'mod_syllabus'),
            'coursename'        => plan_programme_name::resolve((int) $this->course->category),
            'disciplinename'    => format_string($this->course->fullname),
            'teachername'       => plan_teacher_name::resolve($this->syllabus),
            'academicperiod'    => $this->syllabus->academicperiod,
            'coursestartdate'   => $this->syllabus->coursestartdate,
            'courseenddate'     => $this->syllabus->courseenddate,
            'coursestartdatefield' => $this->date_select_field(
                'syllabus-plan-coursestartdate',
                'syllabus-plan-coursestartdate',
                'coursestartdate',
                $coursestartdate,
                $datesweredefaulted
            ),
            'courseenddatefield' => $this->date_select_field(
                'syllabus-plan-courseenddate',
                'syllabus-plan-courseenddate',
                'courseenddate',
                $courseenddate,
                $datesweredefaulted
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
            // Gated on the course module's own visibility, not literally STATUS_APPROVED: a
            // structural edit can reopen an approved plan for review (status regresses to
            // submitted) without ever hiding it, and unpublish() must still be reachable
            // during that window - see plan_state_manager::unpublish().
            'canunpublish'      => (bool) $this->cm->visible && ($canreview || $isauthor),
            'changesrequestedreason' => $status === plan_state_manager::STATUS_CHANGES_REQUESTED
                ? $this->syllabus->changesrequestedreason
                : null,
            // Shown to whoever is reviewing the plan currently awaiting a decision — cleared by
            // plan_state_manager::approve() the same way changesrequestedreason is, so a stale
            // note from a much earlier cycle never resurfaces after the plan regresses through
            // changes_requested again.
            'resubmissionnote' => $status === plan_state_manager::STATUS_SUBMITTED
                ? $this->syllabus->resubmissionnote
                : null,
            // Only meaningful while the author can still act on a changes_requested plan — the
            // textarea that feeds submit_plan's optional 'note' param.
            'showresubmissionnoteinput' => $caneditcontent && $status === plan_state_manager::STATUS_CHANGES_REQUESTED,
        ];

        $planfielddata = plan_handler::create()->get_instance_data($this->syllabus->id, true);
        // The Final assessment's narrative field lives in the same 'plan' Custom Field
        // area as the others, but is rendered in its own section below, not intermixed
        // with Ementa/Objectives/etc. in the generic planfields loop.
        $finalassessmentfielddata = [];
        foreach ($planfielddata as $fieldid => $datacontroller) {
            if ($datacontroller->get_field()->get('shortname') === 'finalassessmentinstructions') {
                $finalassessmentfielddata = [$fieldid => $datacontroller];
                unset($planfielddata[$fieldid]);
                break;
            }
        }

        if ($caneditcontent) {
            $data->planfields = $reader->export_editable_fields($planfielddata, 'plan', $reviewnotes);
            $exportedfinalassessmentfield = $reader->export_editable_fields(
                $finalassessmentfielddata,
                'plan',
                $reviewnotes
            );
            $data->finalassessmentfield = $exportedfinalassessmentfield[0] ?? null;
            $data->structuralhelp = (object) $reader->structural_help();
            $data->finalassessmenttitle = $this->syllabus->finalassessmenttitle;
            $data->finalassessmenttypeoptions = $this->build_select_options(
                self::TYPE_OPTIONS,
                $this->syllabus->finalassessmenttype
            );
            $data->finalassessmentstartdatefield = $this->date_select_field(
                'syllabus-plan-finalassessmentstartdate',
                'syllabus-plan-finalassessmentstartdate',
                'finalassessmentstartdate',
                $this->syllabus->finalassessmentstartdate,
                false,
                true
            );
            $data->finalassessmentenddatefield = $this->date_select_field(
                'syllabus-plan-finalassessmentenddate',
                'syllabus-plan-finalassessmentenddate',
                'finalassessmentenddate',
                $this->syllabus->finalassessmentenddate,
                false,
                true
            );
            $data->finalassessmentpoints = $this->syllabus->finalassessmentpoints;
            $data->grademethodoptions = $this->build_select_options(
                self::GRADEMETHOD_OPTIONS,
                $this->syllabus->grademethod
            );
            $data->stages = array_map(
                fn (int $stagenum): stdClass => (object) ['stagenum' => $stagenum],
                range(1, $stagecount)
            );
            // Adding a week/activity creates the row on the server immediately and reloads
            // with the full fieldset open, so an empty plan needs no client-built placeholder
            // row — the button itself is the only affordance required.
            $data->hasweeks = !empty($weeks);
            $data->weeks = $this->export_editable_weeks($reader, $weeks, $stagecount, $reviewnotes);
            $data->tinyavailable = narrative_editor::is_tiny_available();
            if ($data->tinyavailable) {
                $data->tinyconfig = json_encode(narrative_editor::base_config($context));
            }
        } else {
            $data->readweeks = plan_read_export::weeks($reader, $weeks, true, $canreview, $reviewnotes);
            $data->hasweeks = !empty($data->readweeks);
            $finalassessment = plan_read_export::final_assessment(
                $this->syllabus,
                $narrative,
                $canreview,
                $reader,
                $finalassessmentfielddata,
                $reviewnotes
            );
            $data->finalassessment = $finalassessment;
            $data->hasfinalassessment = trim((string) $finalassessment->title) !== '';
            $schedule = $reader->schedule($weeks);
            $data->schedule = $schedule;
            $data->hasschedule = !empty($schedule);

            // The coordinator's review notes are rendered inline, directly under each
            // plan-level narrative field's own read-only block — never as a separate panel:
            // a professor who also holds mod/syllabus:review (e.g. testing with an admin
            // account) already sees the badge inline via narrative_field.mustache in the
            // caneditcontent branch above, so a second, editable copy at the end would be
            // both redundant and confusing. Scoping this purely to the read branch is what
            // guarantees that.
            if ($canreview) {
                $planreviewnotefields = $reader->review_note_fields($planfielddata, 'plan', $reviewnotes);
                $data->coursedescriptionreviewnote = $planreviewnotefields['coursedescription'] ?? null;
                $data->objectivesreviewnote = $planreviewnotefields['objectives'] ?? null;
                $data->contentsreviewnote = $planreviewnotefields['contents'] ?? null;
                $data->methodologyreviewnote = $planreviewnotefields['methodology'] ?? null;
                $data->presentationscriptreviewnote = $planreviewnotefields['presentationscript'] ?? null;
                $data->generalreferencesreviewnote = $planreviewnotefields['generalreferences'] ?? null;
            }
        }

        return $data;
    }

    /**
     * Reshapes plan_reader::weeks() into the editable-form shape week_row.mustache expects:
     * structural fields as plain values, narrative fields as editable boxes.
     *
     * @param plan_reader $reader Used to build each field's editable-box representation.
     * @param array $weeks Result of plan_reader::weeks().
     * @param int $stagecount The plan's current number of grading stages.
     * @param array $reviewnotes Result of plan_reader::review_notes().
     * @return array
     */
    private function export_editable_weeks(
        plan_reader $reader,
        array $weeks,
        int $stagecount,
        array $reviewnotes
    ): array {
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
                    'typeoptions'       => $this->build_select_options(self::TYPE_OPTIONS, $activity->type),
                    'categoryoptions'   => $this->build_select_options(self::CATEGORY_OPTIONS, $activity->category),
                    'fields'            => $reader->export_editable_fields($activity->fields, 'activity', $reviewnotes),
                ];
            }
            $result[] = (object) [
                'id'            => $week->id,
                'title'         => $week->title,
                'duration'      => $week->duration,
                'startdate'     => $week->startdate,
                'enddate'       => $week->enddate,
                'startdatefield' => $this->date_select_field(
                    'syllabus-week-startdate-' . $week->id,
                    'syllabus-week-startdate',
                    'weekstartdate',
                    $week->startdate,
                    false,
                    true
                ),
                'enddatefield'  => $this->date_select_field(
                    'syllabus-week-enddate-' . $week->id,
                    'syllabus-week-enddate',
                    'weekenddate',
                    $week->enddate,
                    false,
                    true
                ),
                'syncdate'      => $week->syncdate,
                'synclink'      => $week->synclink,
                'synctopic'     => $week->synctopic,
                'stage'         => (int) $week->stage,
                'stageoptions'  => $this->build_stage_options($stagecount, (int) $week->stage),
                'fields'        => $reader->export_editable_fields($week->fields, 'week', $reviewnotes),
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
     * Builds the option list for the Stage `<select>` on a week, 1..$stagecount. If the
     * week's currently stored stage is now out of range (the plan's stagecount was lowered
     * after the week was assigned to a higher stage), it is appended as an extra selected
     * option instead of being silently reassigned — same never-lose-data idea as
     * build_select_options()'s legacy-value fallback, just for a numeric range instead of a
     * fixed token map.
     *
     * @param int $stagecount The plan's current number of grading stages.
     * @param int $current The stage currently stored on the week.
     * @return stdClass[]
     */
    private function build_stage_options(int $stagecount, int $current): array {
        $options = [];
        $matched = false;
        for ($stagenum = 1; $stagenum <= $stagecount; $stagenum++) {
            $selected = $stagenum === $current;
            $matched = $matched || $selected;
            $options[] = (object) [
                'value'    => $stagenum,
                'label'    => get_string('stagen', 'mod_syllabus', $stagenum),
                'selected' => $selected,
            ];
        }
        if (!$matched) {
            $options[] = (object) [
                'value'    => $current,
                'label'    => get_string('stagen', 'mod_syllabus', $current)
                    . ' ' . get_string('stageoutofrange', 'mod_syllabus'),
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
     * @param bool $autosavedefault Whether $timestamp is only a starting suggestion (never
     *     actually saved) that the client should autosave once, right after hydrating.
     * @param bool $trackrequired Whether this date's 3 selects count toward the rail's fill
     *     heuristic (see plan_navigator.js::updateSectionStates()) — deliberately opt-in per
     *     call site, since date_select.mustache is shared by fields the rail was never meant
     *     to track (e.g. Characterisation's course period, week/activity dates).
     * @return stdClass
     */
    private function date_select_field(
        string $fieldid,
        string $fieldclass,
        string $labelkey,
        ?int $timestamp,
        bool $autosavedefault = false,
        bool $trackrequired = false
    ): stdClass {
        return (object) [
            'fieldid'         => $fieldid,
            'fieldclass'      => $fieldclass,
            'labeltext'       => get_string($labelkey, 'mod_syllabus'),
            'timestamp'       => $timestamp,
            'autosavedefault' => $autosavedefault,
            'trackrequired'   => $trackrequired,
        ];
    }
}
