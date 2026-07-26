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
 * mod_syllabus data generator.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Syllabus module data generator class.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_syllabus_generator extends testing_module_generator {
    /**
     * Defaults the Characterisation and Final assessment structural fields
     * plan_completeness_checker requires by default, whenever a caller does not explicitly
     * pass them. Without this, every test that generates a plan via create_module() and later
     * submits it for review (most of the plugin's test suite) would need to fill in these
     * fields itself just to get past submit()'s new required-content check.
     *
     * @param \stdClass|array|null $record
     * @param array|null $options
     * @return \stdClass Module info.
     */
    #[\Override]
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $now = time();
        $defaults = [
            'academicperiod'           => '2026.1',
            'coursestartdate'          => $now,
            'courseenddate'            => $now + WEEKSECS,
            'totalduration'            => 30,
            'finalassessmenttitle'     => 'Final assessment',
            'finalassessmenttype'      => 'quiz',
            'finalassessmentstartdate' => $now,
            'finalassessmentenddate'   => $now + WEEKSECS,
            'finalassessmentpoints'    => 100,
        ];
        foreach ($defaults as $name => $value) {
            if (!property_exists($record, $name)) {
                $record->$name = $value;
            }
        }

        return parent::create_instance($record, $options);
    }
}
