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
 * External service definitions for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_syllabus_save_week' => [
        'classname'    => 'mod_syllabus\external\save_week',
        'methodname'   => 'execute',
        'description'  => 'Create or update a week in a syllabus plan.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit',
    ],
    'mod_syllabus_delete_week' => [
        'classname'    => 'mod_syllabus\external\delete_week',
        'methodname'   => 'execute',
        'description'  => 'Delete a week and its activities from a syllabus plan.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit',
    ],
    'mod_syllabus_save_activity' => [
        'classname'    => 'mod_syllabus\external\save_activity',
        'methodname'   => 'execute',
        'description'  => 'Create or update an activity within a syllabus week.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit',
    ],
    'mod_syllabus_delete_activity' => [
        'classname'    => 'mod_syllabus\external\delete_activity',
        'methodname'   => 'execute',
        'description'  => 'Delete an activity from a syllabus week.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit',
    ],
    'mod_syllabus_save_customfield_value' => [
        'classname'    => 'mod_syllabus\external\save_customfield_value',
        'methodname'   => 'execute',
        'description'  => 'Autosave the value of one narrative Custom Field.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit',
    ],
    'mod_syllabus_save_plan_details' => [
        'classname'    => 'mod_syllabus\external\save_plan_details',
        'methodname'   => 'execute',
        'description'  => 'Autosave the plan-level Characterisation and Presentation fields.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit',
    ],
    'mod_syllabus_save_final_assessment' => [
        'classname'    => 'mod_syllabus\external\save_final_assessment',
        'methodname'   => 'execute',
        'description'  => 'Autosave the plan-level Final assessment block.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit',
    ],
    'mod_syllabus_submit_plan' => [
        'classname'    => 'mod_syllabus\external\submit_plan',
        'methodname'   => 'execute',
        'description'  => 'Submit a syllabus plan for coordination review.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit',
    ],
    'mod_syllabus_review_plan' => [
        'classname'    => 'mod_syllabus\external\review_plan',
        'methodname'   => 'execute',
        'description'  => 'Approve a plan or send it back with requested changes.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:review',
    ],
    'mod_syllabus_unpublish_plan' => [
        'classname'    => 'mod_syllabus\external\unpublish_plan',
        'methodname'   => 'execute',
        'description'  => 'Pull an approved plan back to draft.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/syllabus:submit,mod/syllabus:review',
    ],
];
