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

use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;

/**
 * Restore steps for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete syllabus structure for restore, including the three Custom Field areas.
 */
class restore_syllabus_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the structure for the syllabus activity.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];

        $paths[] = new restore_path_element('syllabus', '/activity/syllabus');
        $paths[] = new restore_path_element('syllabus_planfield', '/activity/syllabus/planfields/planfield');
        $paths[] = new restore_path_element('syllabus_week', '/activity/syllabus/weeks/week');
        $paths[] = new restore_path_element(
            'syllabus_weekfield',
            '/activity/syllabus/weeks/week/weekfields/weekfield'
        );
        $paths[] = new restore_path_element(
            'syllabus_activity',
            '/activity/syllabus/weeks/week/syllabusactivities/syllabusactivity'
        );
        $paths[] = new restore_path_element(
            'syllabus_activityfield',
            '/activity/syllabus/weeks/week/syllabusactivities/syllabusactivity/activityfields/activityfield'
        );

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Resolves a backed-up user id to its restored equivalent, or null if unmapped (no user
     * info in the backup, or the referenced user was not included/could not be matched).
     *
     * @param mixed $olduserid
     * @return int|null
     */
    private function map_user(mixed $olduserid): ?int {
        if (empty($olduserid)) {
            return null;
        }
        $newuserid = $this->get_mappingid('user', $olduserid);
        return $newuserid ?: null;
    }

    /**
     * Process the syllabus element.
     *
     * @param array $data
     * @return void
     */
    protected function process_syllabus($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->submittedby = $this->map_user($data->submittedby ?? null);
        $data->reviewedby = $this->map_user($data->reviewedby ?? null);
        $data->unpublishedby = $this->map_user($data->unpublishedby ?? null);

        $newitemid = $DB->insert_record('syllabus', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process one plan-area Custom Field value.
     *
     * @param array $data
     * @return void
     */
    protected function process_syllabus_planfield($data) {
        $instanceid = $this->get_new_parentid('syllabus');
        $handler = plan_handler::create();
        $newdataid = $handler->restore_customfield_value($instanceid, $data, $this->task);
        if ($newdataid) {
            $handler->restore_define_structure($this, $newdataid, $data['id']);
        }
    }

    /**
     * Process the week element.
     *
     * @param array $data
     * @return void
     */
    protected function process_syllabus_week($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->syllabusid = $this->get_new_parentid('syllabus');

        $newitemid = $DB->insert_record('syllabus_weeks', $data);

        // The itemname here must match the restore_path_element's own name registered in
        // define_structure() ('syllabus_week', singular) — not the table name — since that
        // is the key get_new_parentid() looks up for every descendant element (weekfield,
        // the activity rows within this week) to resolve their own parent id against.
        $this->set_mapping('syllabus_week', $oldid, $newitemid);
    }

    /**
     * Process one week-area Custom Field value.
     *
     * @param array $data
     * @return void
     */
    protected function process_syllabus_weekfield($data) {
        $instanceid = $this->get_new_parentid('syllabus_week');
        $handler = week_handler::create();
        $newdataid = $handler->restore_customfield_value($instanceid, $data, $this->task);
        if ($newdataid) {
            $handler->restore_define_structure($this, $newdataid, $data['id']);
        }
    }

    /**
     * Process the activity element.
     *
     * @param array $data
     * @return void
     */
    protected function process_syllabus_activity($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->weekid = $this->get_new_parentid('syllabus_week');

        $newitemid = $DB->insert_record('syllabus_activities', $data);
        $this->set_mapping('syllabus_activity', $oldid, $newitemid);
    }

    /**
     * Process one activity-area Custom Field value.
     *
     * @param array $data
     * @return void
     */
    protected function process_syllabus_activityfield($data) {
        $instanceid = $this->get_new_parentid('syllabus_activity');
        $handler = activity_handler::create();
        $newdataid = $handler->restore_customfield_value($instanceid, $data, $this->task);
        if ($newdataid) {
            $handler->restore_define_structure($this, $newdataid, $data['id']);
        }
    }

    /**
     * Actions to run after restoring all elements.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_syllabus', 'intro', null);
    }
}
