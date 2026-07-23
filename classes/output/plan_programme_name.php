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

namespace mod_syllabus\output;

/**
 * Resolves the "Curso" (programme) name for the Characterisation block from the Moodle
 * course's own category — the documents distinguish "Curso" (the broader programme) from
 * "Disciplina" (this specific subject, `course.fullname`), and only the latter has a direct
 * Moodle equivalent; category is the closest existing concept (see SCOPE §17).
 *
 * Reads the category name directly from the database rather than through
 * `core_course_category::get()`: that API enforces its own capability check meant for
 * category *browsing* (confirmed to reject a plain enrolled teacher/tutor/student on
 * Moodle 5.2, even though they already have full access to the course itself). A display
 * label for a course the user can already see should never depend on a separate,
 * unrelated "can browse the category listing" permission.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_programme_name {
    /**
     * Resolves the display name of a course category, or an empty string if it no longer exists.
     *
     * @param int $categoryid The course's category id.
     * @return string
     */
    public static function resolve(int $categoryid): string {
        global $DB;

        $name = $DB->get_field('course_categories', 'name', ['id' => $categoryid]);
        return $name !== false ? format_string($name) : '';
    }
}
