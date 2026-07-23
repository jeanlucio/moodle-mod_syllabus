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

use coding_exception;
use stdClass;

/**
 * Single point of truth for the syllabus plan approval workflow.
 *
 * @package mod_syllabus
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_state_manager {
    /** @var string Only the author edits; the activity is always hidden. */
    public const STATUS_DRAFT = 'draft';

    /** @var string Awaiting coordination review; structural edits are blocked. */
    public const STATUS_SUBMITTED = 'submitted';

    /** @var string Coordination approved; the course module is made visible. */
    public const STATUS_APPROVED = 'approved';

    /** @var string Coordination asked for changes; control returns to the author. */
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    /**
     * Submits a plan for coordination review.
     *
     * Valid from `draft` (first submission) or `changes_requested` (resubmission).
     *
     * @param int $syllabusid ID of the syllabus record.
     * @param int $userid ID of the author submitting the plan.
     * @return void
     * @throws coding_exception If the plan is not in a submittable status.
     */
    public static function submit(int $syllabusid, int $userid): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        $submittablefrom = [self::STATUS_DRAFT, self::STATUS_CHANGES_REQUESTED];
        if (!in_array($plan->status, $submittablefrom, true)) {
            throw new coding_exception('Plan cannot be submitted from its current status.');
        }

        $now = time();
        $plan->status = self::STATUS_SUBMITTED;
        $plan->submittedby = $userid;
        $plan->timesubmitted = $now;
        $plan->timemodified = $now;
        $DB->update_record('syllabus', $plan);
    }

    /**
     * Approves a submitted plan, making the course module visible on first approval.
     *
     * @param int $syllabusid ID of the syllabus record.
     * @param int $reviewerid ID of the coordinator approving the plan.
     * @return void
     * @throws coding_exception If the plan is not awaiting review, or the reviewer is its own author.
     */
    public static function approve(int $syllabusid, int $reviewerid): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        self::require_reviewable($plan, $reviewerid);

        $now = time();
        $plan->status = self::STATUS_APPROVED;
        $plan->reviewedby = $reviewerid;
        $plan->timereviewed = $now;
        $plan->changesrequestedreason = null;
        $plan->timemodified = $now;
        $DB->update_record('syllabus', $plan);
    }

    /**
     * Sends a submitted plan back to the author with a justification.
     *
     * @param int $syllabusid ID of the syllabus record.
     * @param int $reviewerid ID of the coordinator requesting changes.
     * @param string $reason Justification shown to the author.
     * @return void
     * @throws coding_exception If the plan is not awaiting review, or the reviewer is its own author.
     */
    public static function request_changes(int $syllabusid, int $reviewerid, string $reason): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        self::require_reviewable($plan, $reviewerid);

        $now = time();
        $plan->status = self::STATUS_CHANGES_REQUESTED;
        $plan->reviewedby = $reviewerid;
        $plan->timereviewed = $now;
        $plan->changesrequestedreason = $reason;
        $plan->timemodified = $now;
        $DB->update_record('syllabus', $plan);
    }

    /**
     * Reopens an already approved plan for review after a structural field changed.
     *
     * Content fields (Custom Fields API) never call this — only structural columns on
     * `syllabus_aulas`/`syllabus_atividades` do, per the hybrid re-edition rule (SCOPE §3.1).
     * A no-op outside `approved` keeps callers simple: they can call this unconditionally
     * after saving a structural field, without checking the current status themselves.
     *
     * @param int $syllabusid ID of the syllabus record.
     * @return void
     */
    public static function reopen_for_structural_change(int $syllabusid): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        if ($plan->status !== self::STATUS_APPROVED) {
            return;
        }

        $plan->status = self::STATUS_SUBMITTED;
        $plan->timemodified = time();
        $DB->update_record('syllabus', $plan);
    }

    /**
     * Guards the two invariants shared by approve() and request_changes().
     *
     * @param stdClass $plan Syllabus record being reviewed.
     * @param int $reviewerid ID of the user attempting to review it.
     * @return void
     * @throws coding_exception If the plan is not awaiting review, or the reviewer is its own author.
     */
    private static function require_reviewable(stdClass $plan, int $reviewerid): void {
        if ($plan->status !== self::STATUS_SUBMITTED) {
            throw new coding_exception('Plan is not awaiting review.');
        }
        if ((int) $plan->submittedby === $reviewerid) {
            throw new coding_exception('A reviewer cannot approve or request changes on their own submission.');
        }
    }
}
