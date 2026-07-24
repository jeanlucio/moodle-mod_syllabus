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
 * Tab 3 — "Tutor plan": the tutor-facing read-only projection of the plan. Deliberately does
 * NOT repeat the plan-level narrative fields (coursedescription/objectives/contents/
 * methodology) or the full Characterisation — the tutor has Tab 2 ("Student's plan") one
 * click away for that. What this tab adds on top of Tab 2's week/activity content is the
 * tutor-exclusive fields: interaction tools, observations, grading criteria (with answer
 * keys) and tutor accompaniment notes.
 *
 * Never reachable by the student (view.php gates it on mod/syllabus:viewtutorview).
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tab_tutor_plan implements renderable, templatable {
    /** @var stdClass The syllabus record. */
    private stdClass $syllabus;

    /** @var stdClass The course record. */
    private stdClass $course;

    /**
     * Creates the tutor plan tab for a given syllabus instance.
     *
     * @param stdClass $syllabus The syllabus record.
     * @param stdClass $course The course record.
     */
    public function __construct(stdClass $syllabus, stdClass $course) {
        $this->syllabus = $syllabus;
        $this->course = $course;
    }

    /**
     * Exports the reduced Characterisation, weeks/activities (with the tutor-exclusive
     * fields included) and the consolidated schedule.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $reader = new plan_reader($this->syllabus);
        $weeks = $reader->weeks();
        $schedule = $reader->schedule($weeks);
        $weeksexport = plan_read_export::weeks($reader, $weeks, true);
        $stagecount = max(1, (int) $this->syllabus->stagecount);

        return (object) [
            'statuslabel'      => get_string(plan_state_manager::status_string_key($this->syllabus->status), 'mod_syllabus'),
            'statusbadgeclass' => plan_state_manager::status_badge_class($this->syllabus->status),
            'hasmultiplestages' => $stagecount > 1,
            'grademethodlabel' => get_string('grademethod' . $this->syllabus->grademethod, 'mod_syllabus'),
            'coursename'       => plan_programme_name::resolve((int) $this->course->category),
            'disciplinename'   => format_string($this->course->fullname),
            'teachername'      => plan_teacher_name::resolve($this->syllabus),
            'academicperiod'   => $this->syllabus->academicperiod,
            'totalduration'    => $this->syllabus->totalduration,
            'weeks'    => $weeksexport,
            'hasweeks' => !empty($weeks),
            'schedule'    => $schedule,
            'hasschedule' => !empty($schedule),
        ];
    }
}
