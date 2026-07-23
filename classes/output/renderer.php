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
 * Renderer for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use plugin_renderer_base;
use stdClass;

/**
 * Main renderer class for mod_syllabus, one method per role-specific view.
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the full plan view (teacher and coordination).
     *
     * @param stdClass $templatedata Data exported by coordinator_view::export_for_template().
     * @return string HTML rendered output.
     */
    public function render_coordinator_view(stdClass $templatedata): string {
        return $this->render_from_template('mod_syllabus/coordinator_view', $templatedata);
    }

    /**
     * Renders the tutor-facing plan view.
     *
     * @param stdClass $templatedata Data exported by tutor_view::export_for_template().
     * @return string HTML rendered output.
     */
    public function render_tutor_view(stdClass $templatedata): string {
        return $this->render_from_template('mod_syllabus/tutor_view', $templatedata);
    }

    /**
     * Renders the student-facing plan view.
     *
     * @param stdClass $templatedata Data exported by student_view::export_for_template().
     * @return string HTML rendered output.
     */
    public function render_student_view(stdClass $templatedata): string {
        return $this->render_from_template('mod_syllabus/student_view', $templatedata);
    }
}
