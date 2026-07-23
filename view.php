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
 * Displays a syllabus plan, dispatching to the tab(s) available to the current user.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/lib.php');

use core_calendar\type_factory;
use mod_syllabus\local\plan_state_manager;
use mod_syllabus\output\tab_full_plan;
use mod_syllabus\output\tab_student_plan;
use mod_syllabus\output\tab_tutor_plan;

$id = optional_param('id', 0, PARAM_INT);
$s = optional_param('s', 0, PARAM_INT);
$requestedtab = optional_param('tab', '', PARAM_ALPHA);

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

// Tab 1 ("Plano completo") is professor/coordination/admin only — a tutor never sees
// workflow, and a student never sees anything beyond their own tab. Tab 3 ("Plano de
// Tutoria") is everyone except the student. Tab 2 ("Plano do Estudante") is the only tab a
// pure student reaches, and reaches without a tab bar at all.
$availabletabs = [];
if ($isreviewer || $isauthor) {
    $availabletabs['full'] = get_string('tabfullplan', 'mod_syllabus');
}
$availabletabs['student'] = get_string('tabstudentplan', 'mod_syllabus');
if ($isreviewer || $isauthor || $istutor) {
    $availabletabs['tutor'] = get_string('tabtutorplan', 'mod_syllabus');
}

if ($isreviewer || $isauthor) {
    $defaulttab = 'full';
} else if ($istutor) {
    $defaulttab = 'tutor';
} else {
    $defaulttab = 'student';
}

$tab = array_key_exists($requestedtab, $availabletabs) ? $requestedtab : $defaulttab;

$PAGE->set_url('/mod/syllabus/view.php', ['id' => $cm->id, 'tab' => $tab]);
$PAGE->set_title(format_string($syllabus->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

/** @var mod_syllabus\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_syllabus');

if ($tab === 'full') {
    $page = new tab_full_plan($syllabus, $cm, $course);
    $html = $renderer->render_tab_full_plan($page->export_for_template($renderer));
    if ($isauthor) {
        $PAGE->requires->js_call_amd('mod_syllabus/weeks_manager', 'init', [$cm->id]);
        $PAGE->requires->js_call_amd('mod_syllabus/autosave', 'init', [$cm->id]);
        $PAGE->requires->js_call_amd('mod_syllabus/plan_details', 'init', [$cm->id]);
        $PAGE->requires->js_call_amd('mod_syllabus/plan_navigator', 'init');
        $monthnames = array_values(type_factory::get_calendar_instance()->get_months());
        $PAGE->requires->js_call_amd('mod_syllabus/plan_dateselect', 'init', [$monthnames]);
    }
    $PAGE->requires->js_call_amd('mod_syllabus/review', 'init', [$cm->id]);
} else if ($tab === 'tutor') {
    $page = new tab_tutor_plan($syllabus, $course);
    $html = $renderer->render_tab_tutor_plan($page->export_for_template($renderer));
} else {
    $page = new tab_student_plan($syllabus, $course);
    $html = $renderer->render_tab_student_plan($page->export_for_template($renderer));
}

echo $OUTPUT->header();

if (count($availabletabs) > 1) {
    echo html_writer::start_tag('nav', [
        'class' => 'syllabus-tab-nav mb-3',
        'aria-label' => get_string('pluginname', 'mod_syllabus'),
    ]);
    echo html_writer::start_tag('ul', ['class' => 'nav nav-tabs']);
    foreach ($availabletabs as $tabkey => $tablabel) {
        $linkattrs = ['class' => 'nav-link' . ($tabkey === $tab ? ' active' : '')];
        if ($tabkey === $tab) {
            $linkattrs['aria-current'] = 'page';
        }
        $url = new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id, 'tab' => $tabkey]);
        echo html_writer::tag('li', html_writer::link($url, $tablabel, $linkattrs), ['class' => 'nav-item']);
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('nav');
}

echo $html;
echo $OUTPUT->footer();
