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
use mod_syllabus\local\plan_state_manager;

/**
 * tab_visibility_test.php only ever exercises edit mode with zero real weeks (the empty-list
 * path) — these tests drive the edit-mode branch with a real, saved week and activity, and
 * cover the workflow status flags (cansubmit/canreviewnow/canunpublish/changesrequestedreason)
 * across every plan status.
 *
 * @coversDefaultClass \mod_syllabus\output\tab_full_plan
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
     * In edit mode with a real saved week/activity, the exported structure reshapes it into
     * the editable-form shape: matched type/category select options are marked selected, date
     * fields carry the real stored timestamp (never the "defaulted" flag), and the Tiny
     * availability flag/config are present.
     *
     * @covers ::export_for_template
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
     * @covers ::export_for_template
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
     * @covers ::export_for_template
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
     * @covers ::export_for_template
     * @covers ::build_stage_options
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
     * @covers ::export_for_template
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
     * @covers ::export_for_template
     * @return void
     */
    public function test_empty_course_dates_are_defaulted_for_the_author(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
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
     * @covers ::export_for_template
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
    }

    /**
     * canreviewnow is true only for a reviewer while the plan is submitted; canunpublish is
     * true for the author or a reviewer once the plan has been approved.
     *
     * @covers ::export_for_template
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

        $page = new tab_full_plan($syllabus, $cm, $course);
        $reviewerdata = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));
        $this->assertFalse($reviewerdata->canreviewnow);
        $this->assertTrue($reviewerdata->canunpublish);

        $this->setUser($teacher);
        $page = new tab_full_plan($syllabus, $cm, $course);
        $this->assertTrue($page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'))->canunpublish);
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
