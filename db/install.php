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
 * Post-installation seed for mod_syllabus's default Custom Fields template.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seeds the four Custom Fields areas (plan, week, activity, help) with the narrative fields of
 * the original three plan documents plus the structural fields' guidance, so a fresh install
 * already looks like today's template. Every field name and description comes from
 * get_string()/help_text_builder::build(), so it is seeded in whatever language is active for
 * the installing session — never hardcoded to one language. Each field's description combines
 * a short summary with the full model guidance behind a disclosure (see help_text_builder) —
 * the institution can freely edit, shorten or rewrite either part later via Site
 * administration > Plugins > Activity modules > Syllabus, without a plugin release.
 *
 * The 'help' area is unlike the other three: its fields never hold a per-instance value (there
 * is no narrative content to author for Characterisation or an activity's type/category — those
 * are structural inputs, not Custom Fields) — only their description is ever read, by
 * plan_reader::structural_help(), purely so the same institution-editable mechanism can cover
 * guidance for fields that have no Custom Field of their own. See customfield_seeder for the
 * shared area definitions and seeding logic — db/upgrade.php reuses the same class to backfill
 * an area onto a site that installed the plugin before that area existed.
 *
 * @return void
 */
function xmldb_syllabus_install(): void {
    foreach (\mod_syllabus\local\customfield_seeder::areas() as $handlerclass => $definition) {
        \mod_syllabus\local\customfield_seeder::seed_area($handlerclass, $definition);
    }
}
