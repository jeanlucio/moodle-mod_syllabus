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
 * Pre-uninstallation hook for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Removes the Custom Fields categories/fields/data that db/install.php seeds.
 *
 * Core automatically drops install.xml tables and admin settings on uninstall, but the
 * four core_customfield areas (plan/week/activity/help) live in core tables the plugin merely
 * writes into — nothing drops them on its own. Without this hook, every uninstall +
 * reinstall cycle re-runs xmldb_syllabus_install() and leaves the previous category/fields/
 * data behind as orphans, so a "fresh" install ends up showing every narrative field
 * duplicated once per past reinstall — e.g. three superimposed copies of "Course
 * description" after three reinstalls.
 *
 * @return void
 */
function xmldb_syllabus_uninstall(): void {
    \mod_syllabus\customfield\plan_handler::create()->delete_all();
    \mod_syllabus\customfield\week_handler::create()->delete_all();
    \mod_syllabus\customfield\activity_handler::create()->delete_all();
    \mod_syllabus\customfield\help_handler::create()->delete_all();
}
