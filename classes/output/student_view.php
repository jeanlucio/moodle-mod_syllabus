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

use mod_syllabus\customfield\plan_handler;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Student-facing plan view: plan-level content only. Never includes grading criteria/
 * answer keys or tutor accompaniment notes, which stay exclusive to tutor_view — the
 * template itself simply never receives those fields, not a value hidden after the fact.
 *
 * @package mod_syllabus
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_view implements renderable, templatable {
    /** @var stdClass The syllabus record. */
    private stdClass $syllabus;

    /**
     * Creates the student view for a given syllabus instance.
     *
     * @param stdClass $syllabus The syllabus record.
     */
    public function __construct(stdClass $syllabus) {
        $this->syllabus = $syllabus;
    }

    /**
     * Exports the plan's plan-level content for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $handler = plan_handler::create();
        $content = $handler->export_instance_data_object($this->syllabus->id);

        return (object) [
            'coursedescription' => $content->coursedescription ?? '',
            'objectives' => $content->objectives ?? '',
            'contents' => $content->contents ?? '',
            'methodology' => $content->methodology ?? '',
        ];
    }
}
