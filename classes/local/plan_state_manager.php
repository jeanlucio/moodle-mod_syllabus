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

use moodle_exception;
use stdClass;

/**
 * Single point of truth for the syllabus plan approval workflow.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Valid from `draft` (first submission) or `changes_requested` (resubmission). Blocked if
     * any field the institution marked required (see plan_completeness_checker) is still empty.
     *
     * @param int $syllabusid ID of the syllabus record.
     * @param int $userid ID of the author submitting the plan.
     * @return void
     * @throws moodle_exception If the plan is not in a submittable status, or required content is missing.
     */
    public static function submit(int $syllabusid, int $userid): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        $submittablefrom = [self::STATUS_DRAFT, self::STATUS_CHANGES_REQUESTED];
        if (!in_array($plan->status, $submittablefrom, true)) {
            throw new moodle_exception('cannotsubmitstatus', 'mod_syllabus');
        }

        $missing = plan_completeness_checker::missing_required_fields($plan);
        if ($missing) {
            throw new moodle_exception('missingrequiredfields', 'mod_syllabus', '', implode('; ', $missing));
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
     * @throws moodle_exception If the plan is not awaiting review, or the reviewer is its own author.
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

        $cm = get_coursemodule_from_instance('syllabus', $syllabusid, $plan->course, false, IGNORE_MISSING);
        if ($cm) {
            set_coursemodule_visible($cm->id, 1);
        }
    }

    /**
     * Sends a submitted plan back to the author with a justification.
     *
     * @param int $syllabusid ID of the syllabus record.
     * @param int $reviewerid ID of the coordinator requesting changes.
     * @param string $reason Justification shown to the author.
     * @return void
     * @throws moodle_exception If the plan is not awaiting review, or the reviewer is its own author.
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
     * `syllabus_weeks`/`syllabus_activities` do, per the hybrid re-edition rule.
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
     * Pulls an approved plan back to draft and hides its course module again.
     *
     * A deliberate, auditable escape hatch distinct from the "Availability" form field, which
     * is permanently frozen (see mod_form.php) precisely so visibility never changes as a side
     * effect of an ordinary settings edit — this method is the only supported way to hide an
     * approved plan again. Like submit()/approve()/request_changes(), this class enforces only
     * the workflow's own business rules, never capability checks: the caller (the controller
     * that wires this to a UI action) is responsible for verifying the
     * user holds mod/syllabus:review, or is the author recorded in $plan->submittedby, before
     * calling this — an unrelated teacher who merely shares mod/syllabus:submit on the course
     * must never be able to unpublish someone else's plan.
     *
     * @param int $syllabusid ID of the syllabus record.
     * @param int $userid ID of the user unpublishing the plan, recorded for audit purposes.
     * @return void
     * @throws moodle_exception If the plan is not currently approved.
     */
    public static function unpublish(int $syllabusid, int $userid): void {
        global $DB;

        $plan = $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
        if ($plan->status !== self::STATUS_APPROVED) {
            throw new moodle_exception('onlyapprovedcanunpublish', 'mod_syllabus');
        }

        $now = time();
        $plan->status = self::STATUS_DRAFT;
        $plan->unpublishedby = $userid;
        $plan->timeunpublished = $now;
        $plan->timemodified = $now;
        $DB->update_record('syllabus', $plan);

        $cm = get_coursemodule_from_instance('syllabus', $syllabusid, $plan->course, false, IGNORE_MISSING);
        if ($cm) {
            set_coursemodule_visible($cm->id, 0);
        }
    }

    /**
     * Maps a status constant to its lang string key (e.g. `changes_requested` -> `status_changesrequested`).
     *
     * @param string $status One of the STATUS_* constants.
     * @return string
     */
    public static function status_string_key(string $status): string {
        return 'status_' . str_replace('_', '', $status);
    }

    /**
     * Maps a status constant to the Bootstrap contextual class for its status badge.
     *
     * `bg-secondary` (draft) is paired with a plugin CSS rule forcing dark text in styles.css:
     * Bootstrap 5's default white-on-grey badge text fails WCAG contrast.
     * The other three colours already carry accessible default text colours in Bootstrap 5.
     *
     * @param string $status One of the STATUS_* constants.
     * @return string
     */
    public static function status_badge_class(string $status): string {
        return match ($status) {
            self::STATUS_SUBMITTED => 'bg-info',
            self::STATUS_CHANGES_REQUESTED => 'bg-warning',
            self::STATUS_APPROVED => 'bg-success',
            default => 'bg-secondary',
        };
    }

    /**
     * Guards structural writes to weeks/activities (title, duration, dates, type, category, points).
     *
     * While a plan is `submitted`, the author is blocked from further structural edits (the
     * coordinator is reviewing a fixed snapshot) — narrative Custom Field content stays
     * editable in every status and is never gated by this method. `draft` and
     * `changes_requested` always allow structural edits (the author has full control), and
     * `approved` allows them too, subject to reopen_for_structural_change() on the caller's side.
     *
     * @param stdClass $plan Syllabus record the structural write targets.
     * @return void
     * @throws moodle_exception If the plan is currently awaiting review.
     */
    public static function require_structural_editable(stdClass $plan): void {
        if ($plan->status === self::STATUS_SUBMITTED) {
            throw new moodle_exception('structuraleditblocked', 'mod_syllabus');
        }
    }

    /**
     * Guards the two invariants shared by approve() and request_changes().
     *
     * @param stdClass $plan Syllabus record being reviewed.
     * @param int $reviewerid ID of the user attempting to review it.
     * @return void
     * @throws moodle_exception If the plan is not awaiting review, or the reviewer is its own author.
     */
    private static function require_reviewable(stdClass $plan, int $reviewerid): void {
        if ($plan->status !== self::STATUS_SUBMITTED) {
            throw new moodle_exception('notawaitingreview', 'mod_syllabus');
        }
        if ((int) $plan->submittedby === $reviewerid) {
            throw new moodle_exception('cannotapproveown', 'mod_syllabus');
        }
    }
}
