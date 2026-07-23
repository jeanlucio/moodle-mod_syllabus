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
 * Privacy tests for mod_syllabus.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\tests;

use context_module;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_syllabus\local\plan_state_manager;
use mod_syllabus\privacy\provider;

/**
 * Privacy tests for mod_syllabus.
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Creates a course with a syllabus instance, submitted and approved by two different users.
     *
     * @return array [\stdClass $course, \stdClass $syllabus, \stdClass $submitter, \stdClass $reviewer]
     */
    private function create_reviewed_plan(): array {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $submitter = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();

        plan_state_manager::submit($syllabus->id, (int) $submitter->id);
        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        return [$course, $syllabus, $submitter, $reviewer];
    }

    /**
     * A user who submitted or reviewed a plan has a context; one who did neither does not.
     *
     * @covers \mod_syllabus\privacy\provider::get_contexts_for_userid
     * @covers \mod_syllabus\privacy\provider::get_metadata
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus, $submitter, $reviewer] = $this->create_reviewed_plan();
        $unrelated = $this->getDataGenerator()->create_user();

        $this->assertCount(1, provider::get_contexts_for_userid($submitter->id));
        $this->assertCount(1, provider::get_contexts_for_userid($reviewer->id));
        $this->assertCount(0, provider::get_contexts_for_userid($unrelated->id));

        $collection = new \core_privacy\local\metadata\collection('mod_syllabus');
        $collection = provider::get_metadata($collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * Both the submitter and the reviewer show up as users with data in the context.
     *
     * @covers \mod_syllabus\privacy\provider::get_users_in_context
     * @return void
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus, $submitter, $reviewer] = $this->create_reviewed_plan();
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $userlist = new userlist($context, 'mod_syllabus');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        $this->assertContains((int) $submitter->id, $userids);
        $this->assertContains((int) $reviewer->id, $userids);
    }

    /**
     * Exporting a submitter's data includes a "submitted" entry with a timestamp.
     *
     * @covers \mod_syllabus\privacy\provider::export_user_data
     * @return void
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus, $submitter] = $this->create_reviewed_plan();
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $contextlist = provider::get_contexts_for_userid($submitter->id);
        $approved = new approved_contextlist($submitter, 'mod_syllabus', $contextlist->get_contextids());

        provider::export_user_data($approved);

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'mod_syllabus')]);
        $this->assertNotEmpty($data);
        // Uses property_exists(), not a PHPUnit assertObjectHas*() method: those differ
        // between PHPUnit 9 (assertObjectHasAttribute, deprecated) and 10+
        // (assertObjectHasProperty), and this plugin's doc-comment test style must run
        // unmodified on both (see CLAUDE.md § Automated Tests).
        $this->assertTrue(property_exists($data, 'submitted'));
    }

    /**
     * Deleting all data in a context anonymises submittedby/reviewedby but keeps the plan row.
     *
     * @covers \mod_syllabus\privacy\provider::delete_data_for_all_users_in_context
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus] = $this->create_reviewed_plan();
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        provider::delete_data_for_all_users_in_context($context);

        $plan = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertNull($plan->submittedby);
        $this->assertNull($plan->reviewedby);
        $this->assertSame(plan_state_manager::STATUS_APPROVED, $plan->status, 'The plan itself must survive.');
    }

    /**
     * Deleting a specific user only anonymises that user's own references.
     *
     * @covers \mod_syllabus\privacy\provider::delete_data_for_users
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus, $submitter, $reviewer] = $this->create_reviewed_plan();
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $approved = new approved_userlist($context, 'mod_syllabus', [$submitter->id]);
        provider::delete_data_for_users($approved);

        $plan = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertNull($plan->submittedby);
        $this->assertEquals($reviewer->id, $plan->reviewedby, 'Only the targeted user is anonymised.');
    }

    /**
     * Deleting data for a user anonymises only their own references, everywhere they appear.
     *
     * @covers \mod_syllabus\privacy\provider::delete_data_for_user
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus, $submitter, $reviewer] = $this->create_reviewed_plan();

        $contextlist = provider::get_contexts_for_userid($submitter->id);
        $approved = new approved_contextlist($submitter, 'mod_syllabus', $contextlist->get_contextids());

        provider::delete_data_for_user($approved);

        $plan = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertNull($plan->submittedby);
        $this->assertEquals($reviewer->id, $plan->reviewedby, 'Only the requesting user is anonymised.');
    }
}
