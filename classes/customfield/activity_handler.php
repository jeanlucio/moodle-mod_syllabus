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

namespace mod_syllabus\customfield;

use core_customfield\handler;

/**
 * Custom field handler for the 'activity' area: narrative fields of an activity
 * (student instructions, grading criteria, tutor guidance). Instanceid is the
 * syllabus_activities id.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_handler extends syllabus_handler_base {
    /** @var activity_handler|null Singleton instance, redeclared per class to avoid sharing state. */
    protected static ?handler $singleton = null;

    /**
     * Returns the singleton handler for this area, ignoring itemid (field templates are shared).
     *
     * @param int $itemid Unused — kept for signature compatibility with the base handler.
     * @return handler
     */
    public static function create(int $itemid = 0): handler {
        if (static::$singleton === null) {
            static::$singleton = new static(0);
        }
        return static::$singleton;
    }

    /**
     * Walks from an activity id, via its week, to the owning syllabus id.
     *
     * @param int $instanceid Id of the syllabus_activities record.
     * @return int
     */
    protected function resolve_syllabus_id(int $instanceid): int {
        global $DB;

        $weekid = (int) $DB->get_field('syllabus_activities', 'weekid', ['id' => $instanceid]);
        if (!$weekid) {
            return 0;
        }
        return (int) $DB->get_field('syllabus_weeks', 'syllabusid', ['id' => $weekid]);
    }
}
