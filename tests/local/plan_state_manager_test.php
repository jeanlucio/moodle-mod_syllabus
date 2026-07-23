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
use coding_exception;

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

        $this->expectException(coding_exception::class);
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
     * The author of a plan cannot approve their own submission, even as a reviewer.
     *
     * @return void
     */
    public function test_approve_by_own_submitter_throws(): void {
        $author = $this->getDataGenerator()->create_user();
        $syllabusid = $this->create_plan(plan_state_manager::STATUS_SUBMITTED, (int) $author->id);

        $this->expectException(coding_exception::class);
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

        $this->expectException(coding_exception::class);
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

        $this->expectException(coding_exception::class);
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
