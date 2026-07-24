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
 * Manage the Custom Fields template for one of the three mod_syllabus areas.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$area = required_param('area', PARAM_ALPHA);
$allowedareas = ['plan', 'week', 'activity', 'help'];
if (!in_array($area, $allowedareas, true)) {
    throw new moodle_exception('invalidarea', 'mod_syllabus');
}

admin_externalpage_setup('syllabus_managefields_' . $area);

$handler = core_customfield\handler::get_handler('mod_syllabus', $area);

$output = $PAGE->get_renderer('core_customfield');
$outputpage = new core_customfield\output\management($handler);

echo $output->header();
echo $output->heading(get_string('managefields' . $area, 'mod_syllabus'));
echo $output->render($outputpage);
echo $output->footer();
