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

namespace mod_syllabus\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use stdClass;

/**
 * Privacy provider for mod_syllabus.
 *
 * The plan's content (name, intro, status, narrative Custom Fields) is the course's own
 * pedagogical record, not personal data about an individual — only the three workflow
 * actor references (submittedby/reviewedby/unpublishedby) are. A plan is shared, ongoing
 * institutional content that students and tutors depend on, so deleting a user's data here
 * means anonymising those references (SCOPE §12), never deleting the plan row itself —
 * unlike a typical per-user submission this plugin has no equivalent of.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('syllabus', [
            'submittedby'    => 'privacy:metadata:syllabus:submittedby',
            'timesubmitted'  => 'privacy:metadata:syllabus:timesubmitted',
            'reviewedby'     => 'privacy:metadata:syllabus:reviewedby',
            'timereviewed'   => 'privacy:metadata:syllabus:timereviewed',
            'unpublishedby'  => 'privacy:metadata:syllabus:unpublishedby',
            'timeunpublished' => 'privacy:metadata:syllabus:timeunpublished',
        ], 'privacy:metadata:syllabus');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {syllabus} s ON s.id = cm.instance
                 WHERE s.submittedby = :userid1 OR s.reviewedby = :userid2 OR s.unpublishedby = :userid3";

        $params = [
            'modname'      => 'syllabus',
            'contextlevel' => CONTEXT_MODULE,
            'userid1'      => $userid,
            'userid2'      => $userid,
            'userid3'      => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT s.submittedby, s.reviewedby, s.unpublishedby
                  FROM {syllabus} s
                  JOIN {course_modules} cm ON cm.instance = s.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";
        $params = ['modname' => 'syllabus', 'cmid' => $context->instanceid];

        $userlist->add_from_sql('submittedby', $sql, $params);
        $userlist->add_from_sql('reviewedby', $sql, $params);
        $userlist->add_from_sql('unpublishedby', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('syllabus', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $plan = $DB->get_record('syllabus', ['id' => $cm->instance]);
            if (!$plan) {
                continue;
            }

            $roles = [];
            if ((int) $plan->submittedby === $userid) {
                $roles['submitted'] = (object) [
                    'time' => transform::datetime((int) $plan->timesubmitted),
                ];
            }
            if ((int) $plan->reviewedby === $userid) {
                $roles['reviewed'] = (object) [
                    'time' => transform::datetime((int) $plan->timereviewed),
                ];
            }
            if ((int) $plan->unpublishedby === $userid) {
                $roles['unpublished'] = (object) [
                    'time' => transform::datetime((int) $plan->timeunpublished),
                ];
            }

            if (empty($roles)) {
                continue;
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'mod_syllabus')],
                (object) $roles
            );
        }
    }

    /**
     * Delete all use data which matches the specified context.
     *
     * @param context $context A user context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('syllabus', $context->instanceid);
        if (!$cm) {
            return;
        }

        self::anonymise_plan($DB, $cm->instance);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('syllabus', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        self::anonymise_plan($DB, $cm->instance, $userids);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('syllabus', $context->instanceid);
            if (!$cm) {
                continue;
            }

            self::anonymise_plan($DB, $cm->instance, [$userid]);
        }
    }

    /**
     * Nulls out submittedby/reviewedby/unpublishedby on a plan, optionally only for the given
     * user ids. The plan row itself is never deleted here — it is shared, ongoing course
     * content students and tutors depend on, not a per-user submission (see class docblock).
     *
     * @param \moodle_database $db
     * @param int $syllabusid
     * @param int[]|null $useridsonly Restrict anonymisation to these user ids, or null for all.
     * @return void
     */
    private static function anonymise_plan(\moodle_database $db, int $syllabusid, ?array $useridsonly = null): void {
        $plan = $db->get_record('syllabus', ['id' => $syllabusid]);
        if (!$plan) {
            return;
        }

        // Some DB drivers (e.g. pgsql) return numeric fields as PHP strings; normalise both
        // sides to int before the strict in_array() comparison below.
        if ($useridsonly !== null) {
            $useridsonly = array_map('intval', $useridsonly);
        }

        $matches = function (?int $fieldvalue) use ($useridsonly): bool {
            if (!$fieldvalue) {
                return false;
            }
            return $useridsonly === null || in_array($fieldvalue, $useridsonly, true);
        };

        $update = new stdClass();
        $update->id = $plan->id;
        $changed = false;

        if ($matches((int) $plan->submittedby)) {
            $update->submittedby = null;
            $changed = true;
        }
        if ($matches((int) $plan->reviewedby)) {
            $update->reviewedby = null;
            $changed = true;
        }
        if ($matches((int) $plan->unpublishedby)) {
            $update->unpublishedby = null;
            $changed = true;
        }

        if ($changed) {
            $db->update_record('syllabus', $update);
        }
    }
}
