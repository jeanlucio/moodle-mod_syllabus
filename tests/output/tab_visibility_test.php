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
 * Tests for the per-tab field visibility matrix.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;

/**
 * Proves each tab exports exactly the fields the visibility matrix allows — a vetoed field
 * is never a property of the exported object at all, not merely blank, so a template change
 * can never accidentally render it (the same discipline documented on plan_read_export).
 */
final class tab_visibility_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Sets a Custom Field value directly, mirroring save_customfield_value::execute().
     *
     * @param \core_customfield\handler $handler Handler for the area the field belongs to.
     * @param int $instanceid Syllabus/week/activity ID owning the value.
     * @param string $shortname Field shortname.
     * @param string $value HTML content to save.
     * @return void
     */
    private function set_customfield_value(
        \core_customfield\handler $handler,
        int $instanceid,
        string $shortname,
        string $value
    ): void {
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
     * Seeds a plan with one week and one activity, every narrative field (tutor-exclusive
     * ones included) filled with a distinctive marker string.
     *
     * @return array [\stdClass $syllabus, \stdClass $course]
     */
    private function seed_full_plan(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course' => $course->id,
            'academicperiod' => '2026.1',
        ]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $DB->set_field('syllabus', 'submittedby', $teacher->id, ['id' => $syllabus->id]);
        $syllabus = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);

        $now = time();
        $weekid = $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id,
            'title' => 'Week 1',
            'duration' => 90,
            'startdate' => $now,
            'enddate' => $now + WEEKSECS,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $activityid = $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid,
            'title' => 'Forum kickoff',
            'points' => 10,
            'enddate' => $now + WEEKSECS,
            'isfinalassessment' => 0,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now + 10,
        ]);

        $this->set_customfield_value(plan_handler::create(), $syllabus->id, 'coursedescription', 'PLAN_NARRATIVE_MARKER');
        $this->set_customfield_value(week_handler::create(), $weekid, 'interactiontools', 'TUTOR_ONLY_WEEK_MARKER');
        $this->set_customfield_value(activity_handler::create(), $activityid, 'gradingcriteria', 'TUTOR_ONLY_ACTIVITY_MARKER');
        $this->set_customfield_value(activity_handler::create(), $activityid, 'studentinstructions', 'STUDENT_SAFE_MARKER');

        return [$syllabus, $course];
    }

    /**
     * The student tab never exports the tutor-exclusive week/activity fields — they are
     * absent from the exported object entirely, not merely empty.
     *
     * @covers \mod_syllabus\output\tab_student_plan::export_for_template
     * @covers \mod_syllabus\output\plan_read_export::weeks
     * @return void
     */
    public function test_student_tab_never_exports_tutor_fields(): void {
        [$syllabus, $course] = $this->seed_full_plan();

        $page = new tab_student_plan($syllabus, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertStringContainsString('PLAN_NARRATIVE_MARKER', $data->coursedescription);

        $week = $data->weeks[0];
        $this->assertFalse(property_exists($week, 'interactiontools'), 'Student tab must never receive interactiontools.');
        $this->assertFalse(property_exists($week, 'notes'), 'Student tab must never receive notes.');

        $activity = $week->activities[0];
        $this->assertStringContainsString('STUDENT_SAFE_MARKER', $activity->studentinstructions);
        $this->assertFalse(property_exists($activity, 'gradingcriteria'), 'Student tab must never receive gradingcriteria.');
        $this->assertFalse(property_exists($activity, 'tutorguidance'), 'Student tab must never receive tutorguidance.');
    }

    /**
     * The tutor tab exports the tutor-exclusive fields but never the plan-level narrative
     * content — that lives one click away, on the student tab.
     *
     * @covers \mod_syllabus\output\tab_tutor_plan::export_for_template
     * @return void
     */
    public function test_tutor_tab_exports_tutor_fields_but_not_plan_narrative(): void {
        [$syllabus, $course] = $this->seed_full_plan();

        $page = new tab_tutor_plan($syllabus, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertFalse(property_exists($data, 'coursedescription'), 'Tutor tab must not repeat plan-level narrative fields.');
        $this->assertFalse(property_exists($data, 'objectives'), 'Tutor tab must not repeat plan-level narrative fields.');

        $week = $data->weeks[0];
        $this->assertStringContainsString('TUTOR_ONLY_WEEK_MARKER', $week->interactiontools);

        $activity = $week->activities[0];
        $this->assertStringContainsString('TUTOR_ONLY_ACTIVITY_MARKER', $activity->gradingcriteria);
        $this->assertStringContainsString('STUDENT_SAFE_MARKER', $activity->studentinstructions);
    }

    /**
     * Coordination/admin reviewing Tab 1 without mod/syllabus:submit sees the same full
     * field set as the tutor tab (everything except the workflow being read-only), never
     * an empty "no weeks yet" placeholder — including the Characterisation names (course,
     * discipline, teacher), which this branch did not export before.
     *
     * @covers \mod_syllabus\output\tab_full_plan::export_for_template
     * @return void
     */
    public function test_full_plan_read_mode_exports_full_field_set(): void {
        global $DB;

        [$syllabus, $course] = $this->seed_full_plan();
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');
        $this->setUser($manager);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertFalse($data->caneditcontent);
        $this->assertTrue($data->hasweeks);
        $this->assertSame(format_string($course->fullname), $data->disciplinename);
        $this->assertNotEmpty($data->coursename);
        $this->assertNotEmpty($data->teachername);
        $week = $data->readweeks[0];
        $this->assertStringContainsString('TUTOR_ONLY_WEEK_MARKER', $week->interactiontools);
        $activity = $week->activities[0];
        $this->assertStringContainsString('TUTOR_ONLY_ACTIVITY_MARKER', $activity->gradingcriteria);
    }

    /**
     * A brand-new plan with no weeks yet exports an empty weeks list — the "Add week" button
     * creates the row on the server immediately (with a default title) and reloads with it
     * fully open, so there is no client-built placeholder row to export.
     *
     * @covers \mod_syllabus\output\tab_full_plan::export_for_template
     * @return void
     */
    public function test_edit_mode_with_no_weeks_exports_empty_list(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        $page = new tab_full_plan($syllabus, $cm, $course);
        $data = $page->export_for_template($GLOBALS['PAGE']->get_renderer('mod_syllabus'));

        $this->assertTrue($data->caneditcontent);
        $this->assertFalse($data->hasweeks);
        $this->assertCount(0, $data->weeks);
    }

    /**
     * The consolidated schedule sorts activities by end date, across weeks.
     *
     * @covers \mod_syllabus\output\plan_reader::schedule
     * @return void
     */
    public function test_schedule_sorted_by_enddate(): void {
        global $DB;

        [$syllabus, $course] = $this->seed_full_plan();

        $week = $DB->get_record('syllabus_weeks', ['syllabusid' => $syllabus->id], '*', MUST_EXIST);
        $now = time();
        $DB->insert_record('syllabus_activities', [
            'weekid' => $week->id,
            'title' => 'Earlier activity',
            'enddate' => $now - DAYSECS,
            'isfinalassessment' => 0,
            'sortorder' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $reader = new plan_reader($syllabus);
        $weeks = $reader->weeks();
        $schedule = $reader->schedule($weeks);

        $this->assertCount(2, $schedule);
        $this->assertSame('Earlier activity', $schedule[0]->title);
        $this->assertSame('Forum kickoff', $schedule[1]->title);
    }
}
