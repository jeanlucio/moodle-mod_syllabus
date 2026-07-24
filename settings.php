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
 * Admin settings for mod_syllabus: links to the four Custom Fields template pages.
 *
 * No plugin-wide configuration value exists, so $settings itself is discarded — only the
 * category and the four external pages below are added to the admin tree.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $modsyllabusfolder = new admin_category(
        'modsyllabusfolder',
        new lang_string('pluginname', 'mod_syllabus'),
        $module->is_enabled() === false
    );
    $ADMIN->add('modsettings', $modsyllabusfolder);

    $ADMIN->add('modsyllabusfolder', new admin_externalpage(
        'syllabus_managefields_plan',
        get_string('managefieldsplan', 'mod_syllabus'),
        new moodle_url('/mod/syllabus/managefields.php', ['area' => 'plan']),
        'mod/syllabus:review'
    ));
    $ADMIN->add('modsyllabusfolder', new admin_externalpage(
        'syllabus_managefields_week',
        get_string('managefieldsweek', 'mod_syllabus'),
        new moodle_url('/mod/syllabus/managefields.php', ['area' => 'week']),
        'mod/syllabus:review'
    ));
    $ADMIN->add('modsyllabusfolder', new admin_externalpage(
        'syllabus_managefields_activity',
        get_string('managefieldsactivity', 'mod_syllabus'),
        new moodle_url('/mod/syllabus/managefields.php', ['area' => 'activity']),
        'mod/syllabus:review'
    ));
    $ADMIN->add('modsyllabusfolder', new admin_externalpage(
        'syllabus_managefields_help',
        get_string('managefieldshelp', 'mod_syllabus'),
        new moodle_url('/mod/syllabus/managefields.php', ['area' => 'help']),
        'mod/syllabus:review'
    ));

    $settings = null;
}
