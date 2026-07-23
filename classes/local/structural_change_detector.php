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

use stdClass;

/**
 * Decides whether a week/activity upsert actually changed a structural field.
 *
 * Every column on `syllabus_weeks`/`syllabus_activities` is structural (SCOPE §3.1/§4) — the
 * narrative content lives entirely in Custom Fields instead. So the only question this class
 * answers is whether the submitted values differ from what is already stored, which is what
 * decides whether an edit on an approved plan needs to reopen it for review: an autosave tick
 * that resubmits identical values (e.g. the user tabbed through a field without changing it)
 * must not spuriously flip an approved plan back to `submitted`.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class structural_change_detector {
    /**
     * Whether any of the given fields differ between the stored record and the submitted values.
     *
     * @param stdClass|null $existing The currently stored record, or null for a brand new row.
     * @param array $submitted Field name => submitted value.
     * @param string[] $fields Field names to compare.
     * @return bool True if this is a new row, or an existing row with at least one field changed.
     */
    public static function changed(?stdClass $existing, array $submitted, array $fields): bool {
        if ($existing === null) {
            return true;
        }

        foreach ($fields as $field) {
            $old = $existing->$field ?? null;
            $new = $submitted[$field] ?? null;
            if ((string) $old !== (string) $new) {
                return true;
            }
        }

        return false;
    }
}
