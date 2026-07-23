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

namespace mod_syllabus\local;

use advanced_testcase;
use moodle_exception;

/**
 * Unit tests for the plan_state_manager approval workflow state machine.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\local\plan_state_manager
 */
final class plan_state_manager_test extends advanced_testcase {
    /**
     * Resets the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Inserts a bare syllabus record directly, without going through the module framework.
     *
     * @param string $status Initial status of the plan.
     * @param int|null $submittedby Author who last submitted the plan, if any.
     * @return int ID of the inserted syllabus record.
     */
    private function create_plan(string $status = plan_state_manager::STATUS_DRAFT, ?int $submittedby = null): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $now = time();

        return (int) $DB->insert_record('syllabus', [
            'course'       => $course->id,
            'name'         => 'Test syllabus',
            'intro'        => '',
            'introformat'  => FORMAT_HTML,
            'status'       => $status,
            'submittedby'  => $submittedby,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Reads the current status of a syllabus record back from the database.
     *
     * @param int $syllabusid ID of the syllabus record.
     * @return string
     */
    private function get_status(int $syllabusid): string {
        global $DB;

        return $DB->get_field('syllabus', 'status', ['id' => $syllabusid], MUST_EXIST);
    }

    /**
     * A draft plan can be submitted, recording the author and the submission time.
     *
     * @return void
     */
    public function test_submit_from_draft(): void {
        global $DB;

        $author = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_DRAFT);

        plan_state_manager::submit($syllabusid, (int) $author->id);

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        $this->assertSame(plan_state_manager::STATUS_SUBMITTED, $plan->status);
        $this->assertEquals($author->id, $plan->submittedby);
        $this->assertNotEmpty($plan->timesubmitted);
    }

    /**
     * A plan sent back with changes_requested can be resubmitted.
     *
     * @return void
     */
    public function test_submit_from_changes_requested(): void {
        $author = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_CHANGES_REQUESTED, (int) $author->id);

        plan_state_manager::submit($syllabusid, (int) $author->id);

        $this->assertSame(plan_state_manager::STATUS_SUBMITTED, $this->get_status($syllabusid));
    }

    /**
     * A plan already awaiting review or already approved cannot be submitted again.
     *
     * @return void
     */
    public function test_submit_from_invalid_status_throws(): void {
        $author = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_APPROVED, (int) $author->id);

        $this->expectException(moodle_exception::class);
        plan_state_manager::submit($syllabusid, (int) $author->id);
    }

    /**
     * A submitted plan is approved by a different user, recording reviewer and time.
     *
     * @return void
     */
    public function test_approve_transitions_to_approved(): void {
        global $DB;

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_SUBMITTED, (int) $author->id);

        plan_state_manager::approve($syllabusid, (int) $reviewer->id);

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        $this->assertSame(plan_state_manager::STATUS_APPROVED, $plan->status);
        $this->assertEquals($reviewer->id, $plan->reviewedby);
        $this->assertNotEmpty($plan->timereviewed);
    }

    /**
     * Approving a plan makes its course module visible — the "automatic publication"
     * the whole workflow exists for. Needs a real course module (unlike the other tests
     * here, which only touch the bare syllabus row), so it uses the module generator
     * instead of create_plan().
     *
     * @return void
     */
    public function test_approve_makes_course_module_visible(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        $this->assertEquals(0, $cm->visible, 'A newly created plan must start hidden.');

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        $cm = $DB->get_record('course_modules', ['id' => $cm->id], '*', MUST_EXIST);
        $this->assertEquals(1, $cm->visible, 'Approving the plan must make its course module visible.');
    }

    /**
     * The author of a plan cannot approve their own submission, even as a reviewer.
     *
     * @return void
     */
    public function test_approve_by_own_submitter_throws(): void {
        $author = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_SUBMITTED, (int) $author->id);

        $this->expectException(moodle_exception::class);
        plan_state_manager::approve($syllabusid, (int) $author->id);
    }

    /**
     * A plan that is not awaiting review cannot be approved.
     *
     * @return void
     */
    public function test_approve_when_not_submitted_throws(): void {
        $reviewer = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_DRAFT);

        $this->expectException(moodle_exception::class);
        plan_state_manager::approve($syllabusid, (int) $reviewer->id);
    }

    /**
     * Requesting changes stores the justification and returns control to the author.
     *
     * @return void
     */
    public function test_request_changes_sets_reason_and_status(): void {
        global $DB;

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_SUBMITTED, (int) $author->id);

        plan_state_manager::request_changes($syllabusid, (int) $reviewer->id, 'Fix the bibliography.');

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        $this->assertSame(plan_state_manager::STATUS_CHANGES_REQUESTED, $plan->status);
        $this->assertSame('Fix the bibliography.', $plan->changesrequestedreason);
    }

    /**
     * The author of a plan cannot request changes on their own submission.
     *
     * @return void
     */
    public function test_request_changes_by_own_submitter_throws(): void {
        $author = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_SUBMITTED, (int) $author->id);

        $this->expectException(moodle_exception::class);
        plan_state_manager::request_changes($syllabusid, (int) $author->id, 'Fix it.');
    }

    /**
     * Editing a structural field on an approved plan reopens it for review.
     *
     * @return void
     */
    public function test_reopen_for_structural_change_from_approved(): void {
        $author = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_APPROVED, (int) $author->id);

        plan_state_manager::reopen_for_structural_change($syllabusid);

        $this->assertSame(plan_state_manager::STATUS_SUBMITTED, $this->get_status($syllabusid));
    }

    /**
     * Reopening a plan that is not approved is a no-op, so callers never need to check status first.
     *
     * @return void
     */
    public function test_reopen_for_structural_change_noop_when_not_approved(): void {
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_DRAFT);

        plan_state_manager::reopen_for_structural_change($syllabusid);

        $this->assertSame(plan_state_manager::STATUS_DRAFT, $this->get_status($syllabusid));
    }

    /**
     * An approved plan can be unpublished, recording who did it and when.
     *
     * @return void
     */
    public function test_unpublish_transitions_to_draft(): void {
        global $DB;

        $author = $this->getDataGenerator()->create_user();
        $unpublisher = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_APPROVED, (int) $author->id);

        plan_state_manager::unpublish($syllabusid, (int) $unpublisher->id);

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        $this->assertSame(plan_state_manager::STATUS_DRAFT, $plan->status);
        $this->assertEquals($unpublisher->id, $plan->unpublishedby);
        $this->assertNotEmpty($plan->timeunpublished);
    }

    /**
     * A plan that is not approved cannot be unpublished.
     *
     * @return void
     */
    public function test_unpublish_when_not_approved_throws(): void {
        $unpublisher = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_DRAFT);

        $this->expectException(moodle_exception::class);
        plan_state_manager::unpublish($syllabusid, (int) $unpublisher->id);
    }

    /**
     * Unpublishing an approved plan hides its course module again — the mirror image of
     * test_approve_makes_course_module_visible().
     *
     * @return void
     */
    public function test_unpublish_hides_course_module(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        $cmrow = $DB->get_record('course_modules', ['id' => $cm->id], '*', MUST_EXIST);
        $this->assertEquals(1, $cmrow->visible, 'Sanity check: the plan must be visible before unpublishing it.');

        plan_state_manager::unpublish($syllabus->id, (int) $reviewer->id);

        $cmrow = $DB->get_record('course_modules', ['id' => $cm->id], '*', MUST_EXIST);
        $this->assertEquals(0, $cmrow->visible, 'Unpublishing the plan must hide its course module again.');
    }

    /**
     * Structural fields can be edited outside the 'submitted' status.
     *
     * @return void
     */
    public function test_require_structural_editable_allows_non_submitted_statuses(): void {
        $editable = [
            plan_state_manager::STATUS_DRAFT,
            plan_state_manager::STATUS_CHANGES_REQUESTED,
            plan_state_manager::STATUS_APPROVED,
        ];
        foreach ($editable as $status) {
            plan_state_manager::require_structural_editable((object) ['status' => $status]);
        }
        $this->addToAssertionCount(count($editable));
    }

    /**
     * Structural fields cannot be edited while the plan is awaiting review.
     *
     * @return void
     */
    public function test_require_structural_editable_blocks_submitted(): void {
        $this->expectException(moodle_exception::class);
        plan_state_manager::require_structural_editable((object) ['status' => plan_state_manager::STATUS_SUBMITTED]);
    }

    /**
     * Every status constant maps to the matching status_* lang string key.
     *
     * @return void
     */
    public function test_status_string_key_maps_every_status(): void {
        $this->assertSame('status_draft', plan_state_manager::status_string_key(plan_state_manager::STATUS_DRAFT));
        $this->assertSame('status_submitted', plan_state_manager::status_string_key(plan_state_manager::STATUS_SUBMITTED));
        $this->assertSame('status_approved', plan_state_manager::status_string_key(plan_state_manager::STATUS_APPROVED));
        $this->assertSame(
            'status_changesrequested',
            plan_state_manager::status_string_key(plan_state_manager::STATUS_CHANGES_REQUESTED)
        );
    }
}
