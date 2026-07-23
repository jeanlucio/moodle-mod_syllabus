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

use mod_syllabus\customfield\plano_handler;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Plano de Tutoria view: everything the student sees, plus (from a later phase on)
 * atividade-level grading criteria and tutor accompaniment notes. Never shows the plan's
 * approval workflow status — a tutor only ever reaches this once the plan is approved,
 * enforced independently of course module visibility in view.php.
 *
 * @package mod_syllabus
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutor_view implements renderable, templatable {
    /** @var stdClass The syllabus record. */
    private stdClass $syllabus;

    /**
     * Creates the tutor view for a given syllabus instance.
     *
     * @param stdClass $syllabus The syllabus record.
     */
    public function __construct(stdClass $syllabus) {
        $this->syllabus = $syllabus;
    }

    /**
     * Exports the plan's plano-level content for the template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $handler = plano_handler::create();
        $content = $handler->export_instance_data_object($this->syllabus->id);

        return (object) [
            'name' => format_string($this->syllabus->name),
            'ementa' => $content->ementa ?? '',
            'objetivos' => $content->objetivos ?? '',
            'conteudos' => $content->conteudos ?? '',
            'metodologia' => $content->metodologia ?? '',
        ];
    }
}
