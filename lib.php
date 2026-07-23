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
 * Library functions for mod_syllabus.
 *
 * @package mod_syllabus
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return the features supported by this module.
 *
 * @param string $feature FEATURE_xx constant for the requested feature.
 * @return mixed True if supported, false if not, null if unknown.
 */
function syllabus_supports(string $feature): mixed {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ADMINISTRATION;
        default:
            return null;
    }
}

/**
 * Add a new syllabus instance. The activity always starts hidden and in draft status,
 * regardless of the course module visibility chosen on the creation form — it is only
 * made visible automatically once the plan is approved (see plan_state_manager::approve()).
 *
 * @param stdClass $data Form data submitted by the teacher.
 * @return int The id of the newly inserted record.
 */
function syllabus_add_instance(stdClass $data): int {
    global $DB;

    $now = time();
    $data->status = \mod_syllabus\local\plan_state_manager::STATUS_DRAFT;
    $data->timecreated = $now;
    $data->timemodified = $now;

    return $DB->insert_record('syllabus', $data);
}

/**
 * Update an existing syllabus instance. Only the base mod_form fields (name/intro) are
 * touched here — workflow fields (status, submittedby, etc.) are never edited through the
 * standard activity editing form, only through the approval workflow itself.
 *
 * @param stdClass $data Form data submitted by the teacher.
 * @return bool True on success.
 */
function syllabus_update_instance(stdClass $data): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    return $DB->update_record('syllabus', $data);
}

/**
 * Delete a syllabus instance and all associated aulas, atividades and custom field data.
 *
 * @param int $id Instance id.
 * @return bool True on success.
 */
function syllabus_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists('syllabus', ['id' => $id])) {
        return false;
    }

    $aulaids = $DB->get_fieldset_select('syllabus_aulas', 'id', 'syllabusid = ?', [$id]);
    if ($aulaids) {
        [$aulainsql, $aulainparams] = $DB->get_in_or_equal($aulaids);

        $atividadeids = $DB->get_fieldset_select('syllabus_atividades', 'id', "aulaid $aulainsql", $aulainparams);
        $atividadehandler = \mod_syllabus\customfield\atividade_handler::create();
        foreach ($atividadeids as $atividadeid) {
            $atividadehandler->delete_instance($atividadeid);
        }
        $DB->delete_records_select('syllabus_atividades', "aulaid $aulainsql", $aulainparams);

        $aulahandler = \mod_syllabus\customfield\aula_handler::create();
        foreach ($aulaids as $aulaid) {
            $aulahandler->delete_instance($aulaid);
        }
        $DB->delete_records('syllabus_aulas', ['syllabusid' => $id]);
    }

    $planohandler = \mod_syllabus\customfield\plano_handler::create();
    $planohandler->delete_instance($id);

    $DB->delete_records('syllabus', ['id' => $id]);

    return true;
}
