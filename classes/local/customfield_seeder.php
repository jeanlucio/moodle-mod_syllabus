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

namespace mod_syllabus\local;

use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\field_controller;

/**
 * Definitions and seeding logic for the plugin's four Custom Field areas — shared by
 * db/install.php (seeds all four on a fresh install) and db/upgrade.php (seeds only the
 * areas an already-installed site is still missing, e.g. 'help' for a site that predates it).
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class customfield_seeder {
    /**
     * The four areas: handler class name => category lang key + shortname-to-name-lang-key
     * field map. 'help_handler' fields never hold a per-instance value (see help_handler's own
     * docblock) — only their description is ever read, for structural fields with no
     * narrative Custom Field of their own.
     *
     * @return array
     */
    public static function areas(): array {
        return [
            'plan_handler' => [
                'category' => 'categoryplan',
                'fields' => [
                    'coursedescription' => 'coursedescription',
                    'objectives' => 'objectives',
                    'contents' => 'contents',
                    'methodology' => 'methodology',
                    'presentationscript' => 'presentationscript',
                    'generalreferences' => 'generalreferences',
                ],
            ],
            'week_handler' => [
                'category' => 'categoryweek',
                'fields' => [
                    'details' => 'details',
                    'supportmaterial' => 'supportmaterial',
                    'supplementarymaterial' => 'supplementarymaterial',
                    'interactiontools' => 'interactiontools',
                    'notes' => 'notes',
                ],
            ],
            'activity_handler' => [
                'category' => 'categoryactivity',
                'fields' => [
                    'studentinstructions' => 'studentinstructions',
                    'gradingcriteria' => 'gradingcriteria',
                    'tutorguidance' => 'tutorguidance',
                ],
            ],
            'help_handler' => [
                'category' => 'categoryhelp',
                'fields' => [
                    'characterisation' => 'characterisation',
                    'weekplanning' => 'weekplanning',
                    'syncmeeting' => 'syncmeeting',
                    'activitytype' => 'activitytypecategory',
                    'finalassessment' => 'finalassessment',
                ],
            ],
        ];
    }

    /**
     * Creates one area's category and every field in it, each with its combined
     * summary + full-guidance description (help_text_builder::build()).
     *
     * @param string $handlerclass One of the array keys returned by areas().
     * @param array $definition The matching value from areas().
     * @return void
     */
    public static function seed_area(string $handlerclass, array $definition): void {
        $classname = "mod_syllabus\\customfield\\{$handlerclass}";
        $handler = $classname::create();
        $categoryid = $handler->create_category(get_string($definition['category'], 'mod_syllabus'));
        $category = category_controller::create($categoryid);

        foreach ($definition['fields'] as $shortname => $namekey) {
            $record = (object) [
                'shortname' => $shortname,
                'name' => get_string($namekey, 'mod_syllabus'),
                'type' => 'textarea',
                'categoryid' => $categoryid,
                'configdata' => [],
                'description' => help_text_builder::build($shortname),
                'descriptionformat' => FORMAT_HTML,
            ];
            $field = field_controller::create(0, $record, $category);
            api::save_field_configuration($field, $record);
        }
    }
}
