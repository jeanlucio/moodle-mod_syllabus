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
 * Tests for tab_full_plan, beyond the visibility matrix already covered by
 * tab_visibility_test.php.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use advanced_testcase;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\local\plan_state_manager;

/**
 * tab_visibility_test.php only ever exercises edit mode with zero real weeks (the empty-list
 * path) — these tests drive the edit-mode branch with a real, saved week and activity, and
 * cover the workflow status flags (cansubmit/canreviewnow/canunpublish/changesrequestedreason)
 * across every plan status.
 *
 * @covers \mod_syllabus\output\tab_full_plan
 * @covers \mod_syllabus\local\plan_state_manager
 */
final class tab_full_plan_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Seeds a course with a syllabus, one week and one activity, and enrols a teacher who can
     * edit its content.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: \stdClass} Syllabus, cm, course, teacher.
     */
    private function seed_editable_plan(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $now = time();
        $DB->insert_record('syllabus_weeks', [
            'syllabusid'   => $syllabus->id,
            'title'        => 'Week 1',
            'duration'     => 8,
            'startdate'    => $now,
            'enddate'      => $now + WEEKSECS,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $weekid = $DB->get_field('syllabus_weeks', 'id', ['syllabusid' => $syllabus->id], MUST_EXIST);
        $DB->insert_record('syllabus_activities', [
            'weekid'       => $weekid,
            'title'        => 'Kickoff forum',
            'type'         => 'forum',
            'category'     => 'synchronous',
            'startdate'    => $now,
            'enddate'      => $now + WEEKSECS,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        return [$syllabus, $cm, $course, $teacher];
    }

    /**
     * Sets a Custom Field value directly, mirroring save_customfield_value::execute() — same
     * helper already used by plan_reader_test.php/plan_snapshot_test.php.
     *
     * @param int $instanceid
     * @param string $shortname
     * @param string $value
     * @return void
     */
    private function set_plan_customfield_value(int $instanceid, string $shortname, string $value): void {
        $handler = plan_handler::create();
        foreach ($handler->get_instance_data($instanceid, true) as $datacontroller) {
            if ($datacontroller->get_field()->get('shortname') !== $shortname) {
                continue;
            }
            if (!$datacontroller->get('id')) {
                $datacontroller->set('contextid', $handler->get_instance_context($instanceid)->id);
            }
            $fakeform = new \stdClass();
            $fakeform->{$datacontroller->get_form_element_name()} = [
                'text' => $value, 'format' => FORMAT_HTML, 'itemid' => 0,
            ];
            $datacontroller->instance_form_save($fakeform);
            return;
        }
        $this->fail("Seeded field '{$shortname}' not found.");
    }

    /**
     * In edit mode with a real saved week/activity, the exported structure reshapes it into
     * the editable-form shape: matched type/category select options are marked selected, date
     * fields carry the real stored timestamp (never the "defaulted" flag), and the Tiny
     * availability flag/config are present.
     *
     * @return void
     */
    public function test_edit_mode_with_real_week_exports_editable_structure(): void {
        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $this->setUser($teacher);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertTrue($data->caneditcontent);
        $this->assertTrue($data->hasweeks);
        $this->assertCount(1, $data->weeks);

        $week = $data->weeks[0];
        $this->assertSame('Week 1', $week->title);
        $this->assertFalse($week->enddatefield->autosavedefault);
        $this->assertEquals($week->enddate, $week->enddatefield->timestamp);
        $this->assertCount(1, $week->activities);

        $activity = $week->activities[0];
        $this->assertSame('Kickoff forum', $activity->title);
        $this->assertFalse($activity->enddatefield->autosavedefault);
        $this->assertEquals($activity->enddate, $activity->enddatefield->timestamp);

        $typeselected = current(array_filter($activity->typeoptions, fn ($o) => $o->selected));
        $this->assertSame('forum', $typeselected->value);
        $categoryselected = current(array_filter($activity->categoryoptions, fn ($o) => $o->selected));
        $this->assertSame('synchronous', $categoryselected->value);
    }

    /**
     * An activity type/category value outside the fixed option list (legacy data, or an
     * institution-specific value typed before this UI existed) is appended as an extra
     * selected option instead of being silently dropped.
     *
     * @return void
     */
    public function test_build_select_options_appends_unmatched_legacy_value(): void {
        global $DB;

        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $weekid = $DB->get_field('syllabus_weeks', 'id', ['syllabusid' => $syllabus->id], MUST_EXIST);
        $DB->set_field(
            'syllabus_activities',
            'type',
            'legacy_custom_type',
            ['weekid' => $weekid, 'title' => 'Kickoff forum']
        );
        $this->setUser($teacher);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $activity = $data->weeks[0]->activities[0];
        $selected = current(array_filter($activity->typeoptions, fn ($o) => $o->selected));
        $this->assertSame('legacy_custom_type', $selected->value);
        $this->assertSame('legacy_custom_type', $selected->label);
    }

    /**
     * With the default single stage, hasmultiplestages is false and the totals bar's stages
     * array has exactly one entry — the same single points chip the plan showed before this
     * feature existed.
     *
     * @return void
     */
    public function test_single_stage_by_default(): void {
        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $this->setUser($teacher);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertSame(1, $data->stagecount);
        $this->assertFalse($data->hasmultiplestages);
        $this->assertCount(1, $data->stages);
        $this->assertSame(1, $data->weeks[0]->stage);
    }

    /**
     * With more than one stage, hasmultiplestages is true, the totals bar's stages array has
     * one entry per stage, and a week's stage select is built from that range. A week whose
     * stored stage is now higher than stagecount (lowered after the week was assigned) gets
     * that value appended as an extra selected option instead of being silently reassigned.
     *
     * @return void
     */
    public function test_multiple_stages_and_orphaned_week_stage(): void {
        global $DB;

        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $DB->set_field('syllabus', 'stagecount', 2, ['id' => $syllabus->id]);
        $DB->set_field('syllabus', 'grademethod', 'average', ['id' => $syllabus->id]);
        $DB->set_field('syllabus_weeks', 'stage', 5, ['syllabusid' => $syllabus->id]);
        $syllabus = $this->refresh($syllabus);
        $this->setUser($teacher);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertSame(2, $data->stagecount);
        $this->assertTrue($data->hasmultiplestages);
        $this->assertCount(2, $data->stages);

        $week = $data->weeks[0];
        $this->assertSame(5, $week->stage);
        $orphaned = current(array_filter($week->stageoptions, fn ($o) => $o->selected));
        $this->assertSame(5, $orphaned->value);

        $grademethodselected = current(array_filter($data->grademethodoptions, fn ($o) => $o->selected));
        $this->assertSame('average', $grademethodselected->value);
    }

    /**
     * The Final assessment's structured fields (title/type/dates/points) and its narrative
     * Custom Field are exported alongside the rest of the editable form.
     *
     * @return void
     */
    public function test_exports_final_assessment_fields(): void {
        global $DB;

        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $DB->update_record('syllabus', (object) [
            'id'                       => $syllabus->id,
            'finalassessmenttitle'     => 'Final exam',
            'finalassessmenttype'      => 'quiz',
            'finalassessmentpoints'    => 100,
        ]);
        $syllabus = $this->refresh($syllabus);
        $this->setUser($teacher);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertSame('Final exam', $data->finalassessmenttitle);
        $this->assertEquals(100, $data->finalassessmentpoints);
        $typeselected = current(array_filter($data->finalassessmenttypeoptions, fn ($o) => $o->selected));
        $this->assertSame('quiz', $typeselected->value);
        $this->assertNotNull($data->finalassessmentfield);
        $this->assertSame(get_string('studentinstructions', 'mod_syllabus'), $data->finalassessmentfield->name ?? null);
        $this->assertSame('plan', $data->finalassessmentfield->area ?? null);
        // The generic planfields loop must never also render this field — it lives in its own
        // section, not intermixed with Ementa/Objectives/etc.
        $shortnames = array_map(
            fn ($f) => $f->name,
            $data->planfields
        );
        $this->assertNotContains(get_string('studentinstructions', 'mod_syllabus'), $shortnames);
    }

    /**
     * A brand-new plan with neither course date set gets both prefilled with today/+1 month,
     * flagged as a client-side default rather than a real saved value.
     *
     * @return void
     */
    public function test_empty_course_dates_are_defaulted_for_the_author(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course' => $course->id,
            'coursestartdate' => null,
            'courseenddate' => null,
        ]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertTrue($data->coursestartdatefield->autosavedefault);
        $this->assertTrue($data->courseenddatefield->autosavedefault);
        $this->assertNotEmpty($data->coursestartdatefield->timestamp);
        $this->assertGreaterThan($data->coursestartdatefield->timestamp, $data->courseenddatefield->timestamp);
    }

    /**
     * cansubmit is true from draft and changes_requested, false from submitted and approved.
     *
     * @return void
     */
    public function test_cansubmit_by_status(): void {
        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $this->setUser($teacher);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $this->assertTrue($page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'))->cansubmit);

        plan_state_manager::submit($syllabus->id, (int) $teacher->id);
        $syllabus = $this->refresh($syllabus);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $this->assertFalse($page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'))->cansubmit);

        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Please adjust.');
        $syllabus = $this->refresh($syllabus);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));
        $this->assertTrue($data->cansubmit);
        $this->assertSame('Please adjust.', $data->changesrequestedreason);
        $this->assertTrue($data->showresubmissionnoteinput);
    }

    /**
     * The author's resubmission note is shown to whoever is viewing the plan while it awaits
     * review (status submitted), but not before it was written, and not once the coordinator
     * has moved the plan on to a decision (approved).
     *
     * @return void
     */
    public function test_resubmissionnote_visible_only_while_submitted(): void {
        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $this->setUser($teacher);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $this->assertNull($page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'))->resubmissionnote);

        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $teacher->id);
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Please adjust.');
        plan_state_manager::submit($syllabus->id, (int) $teacher->id, 'Adjusted as requested.');
        $syllabus = $this->refresh($syllabus);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));
        $this->assertSame('Adjusted as requested.', $data->resubmissionnote);
        $this->assertFalse($data->showresubmissionnoteinput);

        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);
        $syllabus = $this->refresh($syllabus);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $this->assertNull($page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'))->resubmissionnote);
    }

    /**
     * The reviewer's read branch attaches "changed since last review" indicators once a
     * baseline exists (request_changes() took one) and the plan was resubmitted with real
     * changes: an edited narrative field, an untouched narrative field, a brand-new week, and
     * the pre-existing week/activity left exactly as they were. Never exposed to the author's
     * own edit view, even though the same account also holds the review capability here.
     *
     * @return void
     */
    public function test_reviewer_read_branch_shows_changed_since_review_indicators(): void {
        global $DB;

        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');

        plan_state_manager::submit($syllabus->id, (int) $teacher->id);
        plan_state_manager::request_changes($syllabus->id, (int) $reviewer->id, 'Please adjust the ementa.');

        $this->set_plan_customfield_value($syllabus->id, 'coursedescription', 'Updated ementa text.');
        $now = time();
        $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id, 'title' => 'Week 2 — New', 'duration' => 4,
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        plan_state_manager::submit($syllabus->id, (int) $teacher->id);
        $syllabus = $this->refresh($syllabus);

        $this->setUser($reviewer);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertTrue($data->coursedescriptionchanged);
        $this->assertFalse($data->objectiveschanged);

        $weekbytitle = [];
        foreach ($data->readweeks as $week) {
            $weekbytitle[$week->title] = $week;
        }
        $this->assertTrue($weekbytitle['Week 2 — New']->isnew);
        $this->assertFalse($weekbytitle['Week 1']->isnew);
        $this->assertFalse($weekbytitle['Week 1']->structurechanged);
        $this->assertFalse($weekbytitle['Week 1']->detailschanged);

        // The author's own edit view (caneditcontent) never exposes these, even though the
        // same account also holds mod/syllabus:review here.
        $this->setUser($teacher);
        $authordata = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));
        $this->assertFalse(property_exists($authordata, 'coursedescriptionchanged'));
    }

    /**
     * A plan that has never been through a coordinator decision has no reviewsnapshot to diff
     * against — the reviewer's read branch simply carries no indicators at all, rather than
     * erroring or reporting everything as "changed".
     *
     * @return void
     */
    public function test_reviewer_read_branch_has_no_indicators_before_first_review(): void {
        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');
        plan_state_manager::submit($syllabus->id, (int) $teacher->id);
        $syllabus = $this->refresh($syllabus);

        $this->setUser($reviewer);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertFalse(property_exists($data, 'coursedescriptionchanged'));
        $this->assertFalse(property_exists($data->readweeks[0], 'isnew'));
    }

    /**
     * canreviewnow is true only for a reviewer while the plan is submitted; canunpublish is
     * true for the author or a reviewer once the plan has been approved.
     *
     * @return void
     */
    public function test_canreviewnow_and_canunpublish_by_status(): void {
        global $DB;

        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');

        plan_state_manager::submit($syllabus->id, (int) $teacher->id);
        $syllabus = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);

        $this->setUser($reviewer);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $this->assertTrue($page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'))->canreviewnow);

        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);
        $syllabus = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        // Canunpublish is gated on the course module's own visibility (see plan_state_manager
        // ::unpublish()), which approve() just flipped in the database - the in-memory $cm
        // fetched before approval is now stale and must be re-read.
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $reviewerdata = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));
        $this->assertFalse($reviewerdata->canreviewnow);
        $this->assertTrue($reviewerdata->canunpublish);

        $this->setUser($teacher);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $this->assertTrue($page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'))->canunpublish);
    }

    /**
     * A reviewer's read branch (caneditcontent false, canreview true) attaches an inline
     * coordinator review-note object next to every narrative field — plan-level, week, activity
     * and Final assessment alike — rather than the old consolidated end-of-page panel. The
     * author's own edit-mode export (seed_editable_plan() logs in as the teacher) never carries
     * these properties at all, confirming the inline boxes are exclusive to the reviewer's
     * read-only view.
     *
     * @return void
     */
    public function test_reviewer_read_branch_attaches_inline_review_notes(): void {
        global $DB;

        [$syllabus, $cm, $course, $teacher] = $this->seed_editable_plan();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');

        $planfieldid = $DB->get_field_sql(
            "SELECT f.id
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component AND c.area = :area AND f.shortname = :shortname",
            ['component' => 'mod_syllabus', 'area' => 'plan', 'shortname' => 'coursedescription'],
            MUST_EXIST
        );
        $DB->insert_record('syllabus_review_notes', [
            'syllabusid'   => $syllabus->id,
            'area'         => 'plan',
            'instanceid'   => $syllabus->id,
            'fieldid'      => $planfieldid,
            'note'         => 'Please expand the course description.',
            'reviewerid'   => $reviewer->id,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $this->setUser($reviewer);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertFalse($data->caneditcontent);
        $this->assertTrue($data->canreview);
        $this->assertFalse(property_exists($data, 'reviewnotesplanfields'));
        $this->assertFalse(property_exists($data, 'reviewnotesweeks'));

        $this->assertNotNull($data->coursedescriptionreviewnote);
        $this->assertSame('Please expand the course description.', $data->coursedescriptionreviewnote->coordinatornote);
        $this->assertNotNull($data->objectivesreviewnote);
        $this->assertSame('', $data->objectivesreviewnote->coordinatornote);

        $week = $data->readweeks[0];
        $this->assertNotNull($week->detailsreviewnote);
        $this->assertSame('week', $week->detailsreviewnote->area);
        $activity = $week->activities[0];
        $this->assertNotNull($activity->studentinstructionsreviewnote);
        $this->assertSame('activity', $activity->studentinstructionsreviewnote->area);

        $this->setUser($teacher);
        $authordata = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));
        $this->assertFalse(property_exists($authordata, 'coursedescriptionreviewnote'));
    }

    /**
     * Re-fetches a syllabus record after a workflow transition changed its status.
     *
     * @param \stdClass $syllabus
     * @return \stdClass
     */
    private function refresh(\stdClass $syllabus): \stdClass {
        global $DB;

        return $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
    }
}
