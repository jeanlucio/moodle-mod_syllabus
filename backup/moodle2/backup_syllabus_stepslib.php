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
 * Backup steps for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete syllabus structure for backup, including the three Custom Field areas.
 */
class backup_syllabus_activity_structure_step extends backup_activity_structure_step {
    /**
     * SQL selecting a Custom Field area's data rows for one owning instance.
     *
     * Custom Field values live in shared core tables (customfield_data/_field/_category),
     * addressed by component/area/instanceid — not a plugin-owned foreign key column — so
     * there is no set_source_table() equivalent for them. A raw query filtered by
     * backup::VAR_PARENTID (the owning row's own id, injected once per actual row at backup
     * execution time) is the standard way to feed a repeated nested element from a dynamic
     * set like this.
     *
     * @param string $area One of 'plan', 'week', 'activity'.
     * @return string
     */
    private function customfield_source_sql(string $area): string {
        return "SELECT d.id, f.shortname, f.type, d.value, d.valueformat, d.valuetrust
                   FROM {customfield_data} d
                   JOIN {customfield_field} f ON f.id = d.fieldid
                   JOIN {customfield_category} c ON c.id = f.categoryid
                  WHERE c.component = 'mod_syllabus' AND c.area = '{$area}' AND d.instanceid = ?";
    }

    /**
     * Define the structure for the syllabus activity.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $syllabus = new backup_nested_element('syllabus', ['id'], [
            'name', 'intro', 'introformat', 'status', 'submittedby', 'timesubmitted',
            'reviewedby', 'timereviewed', 'changesrequestedreason', 'unpublishedby',
            'timeunpublished', 'academicperiod', 'coursestartdate', 'courseenddate',
            'totalduration', 'presentationvideourl', 'stagecount', 'grademethod',
            'finalassessmenttitle', 'finalassessmenttype', 'finalassessmentstartdate',
            'finalassessmentenddate', 'finalassessmentpoints', 'timecreated', 'timemodified',
        ]);

        $planfields = new backup_nested_element('planfields');
        $planfield = new backup_nested_element('planfield', ['id'], [
            'shortname', 'type', 'value', 'valueformat', 'valuetrust',
        ]);

        $weeks = new backup_nested_element('weeks');
        $week = new backup_nested_element('week', ['id'], [
            'title', 'duration', 'startdate', 'enddate', 'syncdate', 'synclink', 'synctopic',
            'stage', 'sortorder', 'timecreated', 'timemodified',
        ]);

        $weekfields = new backup_nested_element('weekfields');
        $weekfield = new backup_nested_element('weekfield', ['id'], [
            'shortname', 'type', 'value', 'valueformat', 'valuetrust',
        ]);

        // Named 'syllabusactivity', not 'activity': prepare_activity_structure() already
        // wraps the whole tree in a root element literally called "activity" — reusing that
        // name for this nested element throws base_element_struct_exception (confirmed live).
        $activities = new backup_nested_element('syllabusactivities');
        $activity = new backup_nested_element('syllabusactivity', ['id'], [
            'title', 'type', 'category', 'startdate', 'enddate', 'points',
            'sortorder', 'timecreated', 'timemodified',
        ]);

        $activityfields = new backup_nested_element('activityfields');
        $activityfield = new backup_nested_element('activityfield', ['id'], [
            'shortname', 'type', 'value', 'valueformat', 'valuetrust',
        ]);

        // Build the tree.
        $syllabus->add_child($planfields);
        $planfields->add_child($planfield);

        $syllabus->add_child($weeks);
        $weeks->add_child($week);

        $week->add_child($weekfields);
        $weekfields->add_child($weekfield);

        $week->add_child($activities);
        $activities->add_child($activity);

        $activity->add_child($activityfields);
        $activityfields->add_child($activityfield);

        // Define sources.
        $syllabus->set_source_table('syllabus', ['id' => backup::VAR_ACTIVITYID]);
        $week->set_source_table('syllabus_weeks', ['syllabusid' => backup::VAR_PARENTID]);
        $activity->set_source_table('syllabus_activities', ['weekid' => backup::VAR_PARENTID]);

        // Custom Field values only carry pedagogical content, not personal data of their own —
        // but the plan-level submittedby/reviewedby/unpublishedby id annotations below still
        // gate the whole activity on the userinfo setting the same way core does elsewhere.
        $planfield->set_source_sql($this->customfield_source_sql('plan'), [backup::VAR_PARENTID]);
        $weekfield->set_source_sql($this->customfield_source_sql('week'), [backup::VAR_PARENTID]);
        $activityfield->set_source_sql($this->customfield_source_sql('activity'), [backup::VAR_PARENTID]);

        // All three areas only ever use customfield_textarea fields (see save_customfield_value)
        // — annotate the embedded file area directly rather than resolving a handler per row.
        $planfield->annotate_files('customfield_textarea', 'value', 'id');
        $weekfield->annotate_files('customfield_textarea', 'value', 'id');
        $activityfield->annotate_files('customfield_textarea', 'value', 'id');

        // Id annotations — submittedby/reviewedby/unpublishedby are personal data (see §12).
        if ($userinfo) {
            $syllabus->annotate_ids('user', 'submittedby');
            $syllabus->annotate_ids('user', 'reviewedby');
            $syllabus->annotate_ids('user', 'unpublishedby');
        }

        // File annotations for the standard mod_* intro editor.
        $syllabus->annotate_files('mod_syllabus', 'intro', null);

        return $this->prepare_activity_structure($syllabus);
    }
}
