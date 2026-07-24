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

use mod_syllabus\local\plan_state_manager;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Tab 2 — "Student's plan": the student-facing read-only projection of the plan. Never
 * includes grading criteria/answer keys, tutor accompaniment notes, interaction tools or
 * observations — those fields are simply never exported into this template's data, not
 * hidden after the fact.
 *
 * Accessible to the teacher, coordination, admin and tutor too (as the second of their 3
 * tabs) — only the student reaches this without the surrounding tab bar (view.php decides
 * that), since for the student this is the only thing there is to see.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tab_student_plan implements renderable, templatable {
    /** @var stdClass The syllabus record. */
    private stdClass $syllabus;

    /** @var stdClass The course record. */
    private stdClass $course;

    /**
     * Creates the student plan tab for a given syllabus instance.
     *
     * @param stdClass $syllabus The syllabus record.
     * @param stdClass $course The course record.
     */
    public function __construct(stdClass $syllabus, stdClass $course) {
        $this->syllabus = $syllabus;
        $this->course = $course;
    }

    /**
     * Exports the plan-level content, weeks/activities (student-safe fields only) and the
     * consolidated schedule.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $reader = new plan_reader($this->syllabus);
        $narrative = $reader->plan_narrative();
        $weeks = $reader->weeks();
        $schedule = $reader->schedule($weeks);
        $weeksexport = plan_read_export::weeks($reader, $weeks, false);
        $finalassessments = plan_read_export::final_assessment_activities($weeksexport);

        return (object) [
            'statuslabel'      => get_string(plan_state_manager::status_string_key($this->syllabus->status), 'mod_syllabus'),
            'statusbadgeclass' => plan_state_manager::status_badge_class($this->syllabus->status),
            'coursename'       => plan_programme_name::resolve((int) $this->course->category),
            'disciplinename'   => format_string($this->course->fullname),
            'teachername'      => plan_teacher_name::resolve($this->syllabus),
            'academicperiod'   => $this->syllabus->academicperiod,
            'coursestartdate'  => $this->syllabus->coursestartdate,
            'courseenddate'    => $this->syllabus->courseenddate,
            'totalduration'    => $this->syllabus->totalduration,
            'presentationvideourl' => $this->syllabus->presentationvideourl,
            'coursedescription' => $narrative->coursedescription ?? '',
            'objectives'        => $narrative->objectives ?? '',
            'contents'          => $narrative->contents ?? '',
            'methodology'       => $narrative->methodology ?? '',
            'presentationscript' => $narrative->presentationscript ?? '',
            'generalreferences'  => $narrative->generalreferences ?? '',
            'weeks'    => $weeksexport,
            'hasweeks' => !empty($weeks),
            'finalassessments'    => $finalassessments,
            'hasfinalassessments' => !empty($finalassessments),
            'schedule'    => $schedule,
            'hasschedule' => !empty($schedule),
        ];
    }
}
