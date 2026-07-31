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

namespace mod_syllabus\external;

use context_module;
use core_customfield\handler;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_exception;

/**
 * External function for a coordinator to attach, update or clear a review note on one
 * specific narrative Custom Field of a plan.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_review_note extends external_api {
    /**
     * Describe the parameters expected by this function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'area'       => new external_value(PARAM_ALPHA, 'Custom field area: plan, week or activity'),
            'instanceid' => new external_value(PARAM_INT, 'Syllabus/week/activity ID owning the field'),
            'fieldid'    => new external_value(PARAM_INT, 'Custom field ID'),
            'note'       => new external_value(PARAM_TEXT, 'Note text, empty clears the note', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create, update or clear (empty note) a coordinator review note on one narrative field.
     *
     * @param int $cmid Course module ID.
     * @param string $area Custom field area: plan, week or activity.
     * @param int $instanceid Syllabus/week/activity ID owning the field.
     * @param int $fieldid Custom field ID.
     * @param string $note Note text, empty clears the note.
     * @return array Result with success status and timestamp.
     */
    public static function execute(
        int $cmid,
        string $area,
        int $instanceid,
        int $fieldid,
        string $note
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'       => $cmid,
            'area'       => $area,
            'instanceid' => $instanceid,
            'fieldid'    => $fieldid,
            'note'       => $note,
        ]);

        $cm = get_coursemodule_from_id('syllabus', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/syllabus:review', $context);

        $allowedareas = ['plan', 'week', 'activity'];
        if (!in_array($params['area'], $allowedareas, true)) {
            throw new moodle_exception('invalidarea', 'mod_syllabus');
        }

        $handler = handler::get_handler('mod_syllabus', $params['area']);
        if (!$handler->belongs_to_syllabus($params['instanceid'], $cm->instance)) {
            throw new moodle_exception('invalidarea', 'mod_syllabus');
        }

        $fielddatas = $handler->get_instance_data($params['instanceid'], true);
        if (!array_key_exists($params['fieldid'], $fielddatas)) {
            throw new moodle_exception('invalidfield', 'mod_syllabus');
        }

        $now = time();
        $conditions = [
            'syllabusid' => $cm->instance,
            'area'       => $params['area'],
            'instanceid' => $params['instanceid'],
            'fieldid'    => $params['fieldid'],
        ];
        $existing = $DB->get_record('syllabus_review_notes', $conditions);
        $note = trim($params['note']);

        if ($note === '') {
            if ($existing) {
                $DB->delete_records('syllabus_review_notes', ['id' => $existing->id]);
            }
        } else if ($existing) {
            $existing->note = $note;
            $existing->reviewerid = $USER->id;
            $existing->timemodified = $now;
            $DB->update_record('syllabus_review_notes', $existing);
        } else {
            $record = (object) $conditions;
            $record->note = $note;
            $record->reviewerid = $USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('syllabus_review_notes', $record);
        }

        return [
            'success'  => true,
            'saved_at' => $now,
        ];
    }

    /**
     * Describe the return value of this function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
            'saved_at' => new external_value(PARAM_INT, 'Timestamp when saved'),
        ]);
    }
}
