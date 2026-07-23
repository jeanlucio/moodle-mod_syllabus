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

namespace mod_syllabus\customfield;

use context;
use context_module;
use context_system;
use core_customfield\field_controller;
use core_customfield\handler;
use moodle_url;

/**
 * Shared behaviour for the three mod_syllabus custom field areas (plan, week, activity).
 *
 * Field definitions are configured once, site-wide (handler itemid is always 0, mirroring
 * core_course\customfield\course_handler) — every syllabus instance shares the same template.
 * Only the data values are per-instance, addressed by the instanceid passed to get_instance_data()
 * and friends, which is why each concrete handler must know how to walk from its own instanceid
 * (syllabus id, week id or activity id) back to the owning syllabus, in resolve_syllabus_id().
 *
 * @package mod_syllabus
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class syllabus_handler_base extends handler {
    /**
     * Resolves the owning syllabus instance id for a given data instanceid of this area.
     *
     * @param int $instanceid Instance id in this handler's own area (syllabus/week/activity id).
     * @return int The syllabus id, or 0 if it cannot be resolved (e.g. orphaned/unknown id).
     */
    abstract protected function resolve_syllabus_id(int $instanceid): int;

    /**
     * Field templates are configured site-wide, so the configuration context is always the system.
     *
     * @return context
     */
    public function get_configuration_context(): context {
        return context_system::instance();
    }

    /**
     * URL of the shared field management page for this area.
     *
     * @return moodle_url
     */
    public function get_configuration_url(): moodle_url {
        return new moodle_url('/mod/syllabus/managefields.php', ['area' => $this->get_area()]);
    }

    /**
     * The activity's own module context, resolved from the instanceid via resolve_syllabus_id().
     *
     * Falls back to the system context when the instance does not exist yet (form still being
     * built) or cannot be resolved, mirroring core_course\customfield\course_handler's own
     * fallback for a course being created.
     *
     * @param int $instanceid
     * @return context
     */
    public function get_instance_context(int $instanceid = 0): context {
        if ($instanceid > 0) {
            $syllabusid = $this->resolve_syllabus_id($instanceid);
            if ($syllabusid > 0) {
                $cm = get_coursemodule_from_instance('syllabus', $syllabusid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    return context_module::instance($cm->id);
                }
            }
        }
        return context_system::instance();
    }

    /**
     * Field templates (categories/fields) are configured by whoever can review syllabus plans.
     *
     * Checked at the system context (see get_configuration_context()), so a coordinator whose
     * mod/syllabus:review was only assigned at a category context does NOT gain this — only a
     * site-wide reviewer/manager does. This is deliberate: editing the shared template affects
     * every course, not just the reviewer's own category.
     *
     * @return bool
     */
    public function can_configure(): bool {
        return has_capability('mod/syllabus:review', $this->get_configuration_context());
    }

    /**
     * The plan author can edit content field values, at any point in the workflow.
     *
     * @param field_controller $field
     * @param int $instanceid
     * @return bool
     */
    public function can_edit(field_controller $field, int $instanceid = 0): bool {
        return has_capability('mod/syllabus:submit', $this->get_instance_context($instanceid));
    }

    /**
     * Anyone who can view the activity can view its content field values.
     *
     * @param field_controller $field
     * @param int $instanceid
     * @return bool
     */
    public function can_view(field_controller $field, int $instanceid): bool {
        return has_capability('mod/syllabus:view', $this->get_instance_context($instanceid));
    }
}
