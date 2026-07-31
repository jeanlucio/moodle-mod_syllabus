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
 *
 * @covers \mod_syllabus\privacy\provider
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
     * Inserts a coordinator review note directly on a plan-level field.
     *
     * @param \stdClass $syllabus
     * @param \stdClass $reviewer
     * @return void
     */
    private function add_review_note(\stdClass $syllabus, \stdClass $reviewer): void {
        global $DB;

        $DB->insert_record('syllabus_review_notes', [
            'syllabusid'   => $syllabus->id,
            'area'         => 'plan',
            'instanceid'   => $syllabus->id,
            'fieldid'      => 1,
            'note'         => 'Please expand this section.',
            'reviewerid'   => $reviewer->id,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A user who only ever left a review note (never submitted/reviewed/unpublished the plan
     * itself) still has a context, and shows up in get_users_in_context.
     *
     * @return void
     */
    public function test_review_note_author_has_a_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $noteauthor = $this->getDataGenerator()->create_user();
        $this->add_review_note($syllabus, $noteauthor);

        $this->assertCount(1, provider::get_contexts_for_userid($noteauthor->id));

        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $userlist = new userlist($context, 'mod_syllabus');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $noteauthor->id, $userlist->get_userids());
    }

    /**
     * Exporting a reviewer's data includes their review notes, with the note text.
     *
     * @return void
     */
    public function test_export_user_data_includes_review_notes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus] = $this->create_reviewed_plan();
        $noteauthor = $this->getDataGenerator()->create_user();
        $this->add_review_note($syllabus, $noteauthor);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $contextlist = provider::get_contexts_for_userid($noteauthor->id);
        $approved = new approved_contextlist($noteauthor, 'mod_syllabus', $contextlist->get_contextids());
        provider::export_user_data($approved);

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'mod_syllabus')]);
        $this->assertTrue(property_exists($data, 'reviewnotes'));
        $this->assertSame('Please expand this section.', $data->reviewnotes[0]->note);
    }

    /**
     * Deleting all data in a context deletes every review note row outright — unlike
     * submittedby/reviewedby/unpublishedby, a note's whole substance is the reviewer's own
     * authored text, so anonymising just the author FK would leave the text itself behind.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context_deletes_review_notes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus] = $this->create_reviewed_plan();
        $noteauthor = $this->getDataGenerator()->create_user();
        $this->add_review_note($syllabus, $noteauthor);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('syllabus_review_notes', ['syllabusid' => $syllabus->id]));
    }

    /**
     * Deleting a specific user's data only deletes that user's own review notes, keeping a
     * different reviewer's note on the same plan intact.
     *
     * @return void
     */
    public function test_delete_data_for_users_deletes_only_that_users_review_notes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus] = $this->create_reviewed_plan();
        $noteauthor = $this->getDataGenerator()->create_user();
        $otherauthor = $this->getDataGenerator()->create_user();
        $this->add_review_note($syllabus, $noteauthor);
        $this->add_review_note($syllabus, $otherauthor);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $approved = new approved_userlist($context, 'mod_syllabus', [$noteauthor->id]);
        provider::delete_data_for_users($approved);

        $this->assertEquals(0, $DB->count_records('syllabus_review_notes', ['reviewerid' => $noteauthor->id]));
        $this->assertEquals(1, $DB->count_records('syllabus_review_notes', ['reviewerid' => $otherauthor->id]));
    }

    /**
     * Deleting data for a single user (delete_data_for_user) deletes their review notes too.
     *
     * @return void
     */
    public function test_delete_data_for_user_deletes_review_notes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus] = $this->create_reviewed_plan();
        $noteauthor = $this->getDataGenerator()->create_user();
        $this->add_review_note($syllabus, $noteauthor);

        $contextlist = provider::get_contexts_for_userid($noteauthor->id);
        $approved = new approved_contextlist($noteauthor, 'mod_syllabus', $contextlist->get_contextids());
        provider::delete_data_for_user($approved);

        $this->assertEquals(0, $DB->count_records('syllabus_review_notes', ['reviewerid' => $noteauthor->id]));
    }

    /**
     * A user who submitted or reviewed a plan has a context; one who did neither does not.
     *
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
        // unmodified on both.
        $this->assertTrue(property_exists($data, 'submitted'));
    }

    /**
     * Exporting the reviewer's own data includes a "reviewed" entry with a timestamp —
     * test_export_user_data() above only ever checked the submitter's export.
     *
     * @return void
     */
    public function test_export_user_data_for_reviewer_includes_reviewed_entry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus, , $reviewer] = $this->create_reviewed_plan();
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $contextlist = provider::get_contexts_for_userid($reviewer->id);
        $approved = new approved_contextlist($reviewer, 'mod_syllabus', $contextlist->get_contextids());
        provider::export_user_data($approved);

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'mod_syllabus')]);
        $this->assertTrue(property_exists($data, 'reviewed'));
    }

    /**
     * Deleting all data in a context anonymises submittedby/reviewedby but keeps the plan row.
     *
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

    /**
     * unpublishedby is tracked and anonymised exactly like submittedby/reviewedby — none of
     * the tests above ever unpublish a plan, so that third reference is otherwise never
     * exercised.
     *
     * @return void
     */
    public function test_unpublishedby_is_tracked_and_anonymised(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus] = $this->create_reviewed_plan();
        $unpublisher = $this->getDataGenerator()->create_user();
        plan_state_manager::unpublish($syllabus->id, (int) $unpublisher->id);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $this->assertCount(1, provider::get_contexts_for_userid($unpublisher->id));

        $userlist = new userlist($context, 'mod_syllabus');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $unpublisher->id, $userlist->get_userids());

        $contextlist = provider::get_contexts_for_userid($unpublisher->id);
        $approved = new approved_contextlist($unpublisher, 'mod_syllabus', $contextlist->get_contextids());
        provider::export_user_data($approved);
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'mod_syllabus')]);
        $this->assertTrue(property_exists($data, 'unpublished'));

        provider::delete_data_for_all_users_in_context($context);
        $plan = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertNull($plan->unpublishedby);
    }

    /**
     * get_users_in_context() and delete_data_for_all_users_in_context() both no-op at any
     * context level other than CONTEXT_MODULE, rather than running a query against a context
     * they were never meant to handle.
     *
     * @return void
     */
    public function test_wrong_context_level_is_a_noop(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus] = $this->create_reviewed_plan();
        $systemcontext = \context_system::instance();

        $userlist = new userlist($systemcontext, 'mod_syllabus');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist->get_userids());

        provider::delete_data_for_all_users_in_context($systemcontext);
        $plan = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertNotNull($plan->submittedby, 'A system-context call must never touch module data.');
    }

    /**
     * export_user_data() and delete_data_for_user() both no-op on an empty approved contextlist,
     * and delete_data_for_users() no-ops on an empty approved userlist — none of these ever
     * reach the database.
     *
     * @return void
     */
    public function test_empty_lists_are_a_noop(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [, $syllabus, $submitter] = $this->create_reviewed_plan();
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $emptycontextlist = new approved_contextlist($submitter, 'mod_syllabus', []);
        provider::export_user_data($emptycontextlist);
        $this->assertEmpty(writer::with_context($context)->get_data([get_string('pluginname', 'mod_syllabus')]));

        provider::delete_data_for_user($emptycontextlist);
        $emptyuserlist = new approved_userlist($context, 'mod_syllabus', []);
        provider::delete_data_for_users($emptyuserlist);

        $plan = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertNotNull($plan->submittedby, 'Empty approved lists must never anonymise anything.');
    }
}
