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
 * Custom field handler for the 'help' area: guidance text for the structural fields that have
 * no narrative Custom Field of their own — Characterisation, a week's workload/period,
 * Synchronous meeting, an activity's type/category, and Final assessment. Unlike the plan/
 * week/activity areas, these fields never hold a per-instance value: only their `description`
 * is ever read, by plan_reader::structural_help(), so the institution can edit the same
 * "summary + full model guidance" text managefields.php already gives the narrative fields,
 * without a plugin release.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class help_handler extends syllabus_handler_base {
    /** @var help_handler|null Singleton instance, redeclared per class to avoid sharing state. */
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
     * This area has no per-instance values (see class docblock) — there is no syllabus to
     * resolve to, so this is never meaningfully called.
     *
     * @param int $instanceid Unused.
     * @return int Always 0.
     */
    protected function resolve_syllabus_id(int $instanceid): int {
        return 0;
    }
}
