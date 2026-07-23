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
 * Activity configuration form for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Form for creating or editing a syllabus activity instance.
 *
 * Deliberately minimal: only the base mod_* metadata (name, description). The plan's own
 * content (course description, weeks, activities) is authored afterwards on the activity's
 * own page, with autosave — never through this one-shot form.
 */
class mod_syllabus_mod_form extends moodleform_mod {
    /**
     * Define the form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'mod_syllabus'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $this->standard_coursemodule_elements();
        $this->lock_visibility_element();
        $this->add_action_buttons();
    }

    /**
     * Freeze the "Availability" element added by standard_coursemodule_elements().
     *
     * The activity's course module visibility is not a teacher-facing setting: it is set
     * entirely by the plan's own approval workflow (see plan_state_manager::approve() and
     * syllabus_add_instance()), so leaving it editable here would let a teacher bypass that
     * workflow and publish an unapproved plan early via the ordinary activity settings form.
     *
     * @return void
     */
    private function lock_visibility_element(): void {
        $mform = $this->_form;

        $mform->hardFreeze('visible');

        $notice = html_writer::div(get_string('visibilitymanaged', 'mod_syllabus'), 'alert alert-warning');
        $mform->insertElementBefore(
            $mform->createElement('static', 'visibilitymanagednotice', '', $notice),
            'cmidnumber'
        );
    }
}
