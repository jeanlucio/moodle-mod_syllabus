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

/**
 * Displays a syllabus plan, dispatching to a role-specific view.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/lib.php');

use mod_syllabus\local\plan_state_manager;
use mod_syllabus\output\coordinator_view;
use mod_syllabus\output\student_view;
use mod_syllabus\output\tutor_view;

$id = optional_param('id', 0, PARAM_INT);
$s = optional_param('s', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $syllabus = $DB->get_record('syllabus', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $syllabus = $DB->get_record('syllabus', ['id' => $s], '*', MUST_EXIST);
    $course = get_course($syllabus->course);
    $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/syllabus:view', $context);

$isreviewer = has_capability('mod/syllabus:review', $context);
$isauthor = has_capability('mod/syllabus:submit', $context);
$istutor = has_capability('mod/syllabus:viewtutorview', $context);

// Content is gated by the plan's own status, independent of the course module's visibility
// flag: a hidden-but-approved edge case should never happen, but a reviewer/author must
// always be able to reach a draft regardless of visibility, while a tutor/student never
// reaches a plan that is not yet approved, even if visibility was toggled by mistake.
if (!$isreviewer && !$isauthor && $syllabus->status !== plan_state_manager::STATUS_APPROVED) {
    throw new moodle_exception('plannotavailable', 'mod_syllabus');
}

$PAGE->set_url('/mod/syllabus/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($syllabus->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

/** @var mod_syllabus\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_syllabus');

if ($isreviewer || $isauthor) {
    $page = new coordinator_view($syllabus);
    $html = $renderer->render_coordinator_view($page->export_for_template($renderer));
} else if ($istutor) {
    $page = new tutor_view($syllabus);
    $html = $renderer->render_tutor_view($page->export_for_template($renderer));
} else {
    $page = new student_view($syllabus);
    $html = $renderer->render_student_view($page->export_for_template($renderer));
}

echo $OUTPUT->header();
echo $html;
echo $OUTPUT->footer();
