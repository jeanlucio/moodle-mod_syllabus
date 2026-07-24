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

use html_writer;

/**
 * Builds the seeded HTML combining a field's short summary and its full model guidance behind
 * a "View model guidance" disclosure — used only at seed time (db/install.php, db/upgrade.php)
 * to build the Custom Field `description` an institution then owns and can freely edit via
 * managefields.php. Once seeded, the combined HTML lives entirely in the database; nothing at
 * render time depends on this class or on the "<shortname>helpfull" lang strings it reads.
 *
 * The disclosure deliberately uses plain <div>/<span> with only a `class` attribute, never
 * <details>/<summary> — confirmed live against clean_text() (the same cleaning both
 * core_customfield's save path and format_text() apply) that <details>/<summary> are stripped
 * outright, along with role, tabindex, aria- and data- attributes, and even a <button> tag, on
 * any of the four areas' admin-editable description. amd/src/field_help_toggle.js adds the
 * interactive behaviour and accessibility attributes at render time instead, since none of
 * that can survive being stored as HTML.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class help_text_builder {
    /**
     * Builds the combined description HTML for a given field shortname, from its
     * "<shortname>help" (short summary) and "<shortname>helpfull" (full model guidance) lang
     * strings.
     *
     * @param string $shortname
     * @return string
     */
    public static function build(string $shortname): string {
        $summary = html_writer::tag('p', get_string($shortname . 'help', 'mod_syllabus'));
        $full = html_writer::div(
            html_writer::span(get_string('viewmodelguidance', 'mod_syllabus'), 'syllabus-help-toggle') .
                html_writer::div(
                    html_writer::tag('p', get_string($shortname . 'helpfull', 'mod_syllabus')),
                    'syllabus-help-body'
                ),
            'syllabus-field-help-full'
        );
        return $summary . $full;
    }
}
