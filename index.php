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
 * List of all syllabus instances in a course.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/lib.php');

use mod_syllabus\local\plan_state_manager;

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url('/mod/syllabus/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_syllabus'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->set_context($coursecontext);

$syllabuses = get_all_instances_in_course('syllabus', $course);

$table = new html_table();
$table->head = [get_string('activityname', 'mod_syllabus'), get_string('modulename', 'mod_syllabus')];
$table->attributes['class'] = 'generaltable mod_index';

foreach ($syllabuses as $syllabus) {
    $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
    $modcontext = context_module::instance($cm->id);

    if (!has_capability('mod/syllabus:view', $modcontext)) {
        continue;
    }

    $isreviewerorauthor = has_capability('mod/syllabus:review', $modcontext)
        || has_capability('mod/syllabus:submit', $modcontext);
    if (!$isreviewerorauthor && $syllabus->status !== plan_state_manager::STATUS_APPROVED) {
        continue;
    }

    $link = html_writer::link(
        new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]),
        format_string($syllabus->name)
    );
    $table->data[] = [$link, get_string(plan_state_manager::status_string_key($syllabus->status), 'mod_syllabus')];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_syllabus'));

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('nosyllabusincourse', 'mod_syllabus'), 'info');
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
