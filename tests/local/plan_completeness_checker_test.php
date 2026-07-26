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
use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;
use stdClass;

/**
 * Unit tests for plan_completeness_checker.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\local\plan_completeness_checker
 */
final class plan_completeness_checker_test extends advanced_testcase {
    /**
     * Resets the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->reset_customfield_handler_singletons();
        $settings = [
            'requireacademicperiod', 'requirecourseperiod', 'requiretotalworkload', 'requirefinalassessment',
            'requireweekplanning', 'requireactivitytype', 'requireactivityperiod',
        ];
        foreach ($settings as $setting) {
            set_config($setting, 1, 'mod_syllabus');
        }
    }

    /**
     * Clears the same handler singleton caches tearDown() resets in setUp(), so a field this
     * test class marked required never leaks — as a stale in-memory field_controller, not as a
     * DB row, so resetAfterTest()'s rollback alone can't undo it — into a different test class
     * that happens to run later in the same PHPUnit process and touches the same field.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->reset_customfield_handler_singletons();
        parent::tearDown();
    }

    /**
     * Clears the per-class singleton each concrete handler caches its field definitions on
     * (handler::$categories, set the first time get_fields()/get_instance_data() runs and
     * never invalidated). Handler instances persist for the whole PHPUnit process, not just one
     * test, so a field marked required via mark_field_required() after an earlier test already
     * populated that cache would otherwise be invisible until the next process — this forces a
     * fresh handler (and a fresh read of customfield_field) at the start of every test.
     *
     * @return void
     */
    private function reset_customfield_handler_singletons(): void {
        foreach ([plan_handler::class, week_handler::class, activity_handler::class] as $class) {
            $property = new \ReflectionProperty($class, 'singleton');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }
    }

    /**
     * Creates a plan with every structural field this checker covers already filled in.
     *
     * @return stdClass The syllabus record.
     */
    private function create_complete_plan(): stdClass {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);

        $now = time();
        $DB->update_record('syllabus', (object) [
            'id'                       => $syllabus->id,
            'academicperiod'           => '2026.1',
            'coursestartdate'          => $now,
            'courseenddate'            => $now + WEEKSECS,
            'totalduration'            => 30,
            'finalassessmenttitle'     => 'Final assessment',
            'finalassessmenttype'      => 'quiz',
            'finalassessmentstartdate' => $now,
            'finalassessmentenddate'   => $now + WEEKSECS,
            'finalassessmentpoints'    => 100,
        ]);

        return $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
    }

    /**
     * Adds a complete week (workload/period filled) to a plan.
     *
     * @param int $syllabusid
     * @return int Week id.
     */
    private function add_complete_week(int $syllabusid): int {
        global $DB;

        $now = time();
        return (int) $DB->insert_record('syllabus_weeks', [
            'syllabusid'   => $syllabusid,
            'title'        => 'Week 1',
            'duration'     => 8,
            'startdate'    => $now,
            'enddate'      => $now + WEEKSECS,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Adds a complete activity (type/period filled) to a week.
     *
     * @param int $weekid
     * @return int Activity id.
     */
    private function add_complete_activity(int $weekid): int {
        global $DB;

        $now = time();
        return (int) $DB->insert_record('syllabus_activities', [
            'weekid'       => $weekid,
            'title'        => 'Forum kickoff',
            'type'         => 'forum',
            'startdate'    => $now,
            'enddate'      => $now + WEEKSECS,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Sets a Custom Field value directly, mirroring save_customfield_value::execute().
     *
     * @param \core_customfield\handler $handler
     * @param int $instanceid
     * @param string $shortname
     * @param string $value
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
            $fakeform = new stdClass();
            $fakeform->{$datacontroller->get_form_element_name()} = [
                'text' => $value, 'format' => FORMAT_HTML, 'itemid' => 0,
            ];
            $datacontroller->instance_form_save($fakeform);
            return;
        }
        $this->fail("Seeded field '{$shortname}' not found.");
    }

    /**
     * Marks a Custom Field (by shortname) as required, the same way managefields.php's own
     * edit form does — via the API, not a raw DB write, so any cache the API maintains for
     * field definitions stays in sync with what handlers read back afterwards.
     *
     * @param string $shortname
     * @return void
     */
    private function mark_field_required(string $shortname): void {
        global $DB;

        $fieldid = (int) $DB->get_field('customfield_field', 'id', ['shortname' => $shortname], MUST_EXIST);
        $field = \core_customfield\field_controller::create($fieldid);
        \core_customfield\api::save_field_configuration($field, (object) ['configdata' => ['required' => 1]]);
    }

    /**
     * A fully filled plan with no weeks has nothing missing.
     *
     * @return void
     */
    public function test_complete_plan_has_no_missing_fields(): void {
        $plan = $this->create_complete_plan();

        $this->assertSame([], plan_completeness_checker::missing_required_fields($plan));
    }

    /**
     * An empty Academic period is reported when its setting is enabled.
     *
     * @return void
     */
    public function test_missing_academic_period_flagged(): void {
        global $DB;

        $plan = $this->create_complete_plan();
        $DB->set_field('syllabus', 'academicperiod', '', ['id' => $plan->id]);
        $plan = $DB->get_record('syllabus', ['id' => $plan->id], '*', MUST_EXIST);

        $missing = plan_completeness_checker::missing_required_fields($plan);

        $this->assertContains(get_string('academicperiod', 'mod_syllabus'), $missing);
    }

    /**
     * Disabling a structural field's setting stops it from ever being reported, even empty.
     *
     * @return void
     */
    public function test_academic_period_not_flagged_when_setting_disabled(): void {
        global $DB;

        set_config('requireacademicperiod', 0, 'mod_syllabus');
        $plan = $this->create_complete_plan();
        $DB->set_field('syllabus', 'academicperiod', '', ['id' => $plan->id]);
        $plan = $DB->get_record('syllabus', ['id' => $plan->id], '*', MUST_EXIST);

        $this->assertSame([], plan_completeness_checker::missing_required_fields($plan));
    }

    /**
     * Either half of the course period being empty is enough to flag the whole group.
     *
     * @return void
     */
    public function test_missing_course_period_flagged(): void {
        global $DB;

        $plan = $this->create_complete_plan();
        $DB->set_field('syllabus', 'courseenddate', null, ['id' => $plan->id]);
        $plan = $DB->get_record('syllabus', ['id' => $plan->id], '*', MUST_EXIST);

        $missing = plan_completeness_checker::missing_required_fields($plan);
        $this->assertContains(get_string('courseperiod', 'mod_syllabus'), $missing);
    }

    /**
     * An empty Total workload is reported.
     *
     * @return void
     */
    public function test_missing_total_workload_flagged(): void {
        global $DB;

        $plan = $this->create_complete_plan();
        $DB->set_field('syllabus', 'totalduration', null, ['id' => $plan->id]);
        $plan = $DB->get_record('syllabus', ['id' => $plan->id], '*', MUST_EXIST);

        $missing = plan_completeness_checker::missing_required_fields($plan);
        $this->assertContains(get_string('totalworkload', 'mod_syllabus'), $missing);
    }

    /**
     * Any single empty Final assessment sub-field is enough to flag the whole block.
     *
     * @return void
     */
    public function test_missing_final_assessment_flagged(): void {
        global $DB;

        $plan = $this->create_complete_plan();
        $DB->set_field('syllabus', 'finalassessmentpoints', null, ['id' => $plan->id]);
        $plan = $DB->get_record('syllabus', ['id' => $plan->id], '*', MUST_EXIST);

        $missing = plan_completeness_checker::missing_required_fields($plan);
        $this->assertContains(get_string('finalassessment', 'mod_syllabus'), $missing);
    }

    /**
     * A week's workload/period group is reported prefixed with the week's own title.
     *
     * @return void
     */
    public function test_missing_week_planning_flagged_with_week_title(): void {
        global $DB;

        $plan = $this->create_complete_plan();
        $weekid = $this->add_complete_week($plan->id);
        $DB->set_field('syllabus_weeks', 'duration', null, ['id' => $weekid]);

        $missing = plan_completeness_checker::missing_required_fields($plan);

        $this->assertContains('Week 1: ' . get_string('weekplanning', 'mod_syllabus'), $missing);
    }

    /**
     * An activity's empty Type is reported prefixed with its week and its own title.
     *
     * @return void
     */
    public function test_missing_activity_type_flagged_with_week_and_activity_title(): void {
        global $DB;

        $plan = $this->create_complete_plan();
        $weekid = $this->add_complete_week($plan->id);
        $activityid = $this->add_complete_activity($weekid);
        $DB->set_field('syllabus_activities', 'type', null, ['id' => $activityid]);

        $missing = plan_completeness_checker::missing_required_fields($plan);

        $this->assertContains('Week 1 › Forum kickoff: ' . get_string('activitytype', 'mod_syllabus'), $missing);
    }

    /**
     * Either half of an activity's start/end date being empty flags the whole group.
     *
     * @return void
     */
    public function test_missing_activity_period_flagged(): void {
        global $DB;

        $plan = $this->create_complete_plan();
        $weekid = $this->add_complete_week($plan->id);
        $activityid = $this->add_complete_activity($weekid);
        $DB->set_field('syllabus_activities', 'enddate', null, ['id' => $activityid]);

        $missing = plan_completeness_checker::missing_required_fields($plan);

        $this->assertContains('Week 1 › Forum kickoff: ' . get_string('activityperiod', 'mod_syllabus'), $missing);
    }

    /**
     * A narrative field left empty is reported by its formatted name only when its own
     * core_customfield "required" flag is set — the admin-configured flag this checker reads
     * directly, with no plugin setting of its own.
     *
     * @return void
     */
    public function test_missing_required_narrative_field_flagged(): void {
        $this->mark_field_required('coursedescription');
        $plan = $this->create_complete_plan();

        $missing = plan_completeness_checker::missing_required_fields($plan);

        $this->assertContains(get_string('coursedescription', 'mod_syllabus'), $missing);
    }

    /**
     * A required narrative field is no longer reported once it has a value.
     *
     * @return void
     */
    public function test_filled_required_narrative_field_not_flagged(): void {
        $this->mark_field_required('coursedescription');
        $plan = $this->create_complete_plan();
        $this->set_customfield_value(plan_handler::create(), $plan->id, 'coursedescription', 'A real description.');

        $this->assertSame([], plan_completeness_checker::missing_required_fields($plan));
    }

    /**
     * A required narrative field on a week/activity is reported with the same week/activity
     * title prefix as the structural checks.
     *
     * @return void
     */
    public function test_missing_required_narrative_week_and_activity_fields_flagged(): void {
        $this->mark_field_required('details');
        $this->mark_field_required('studentinstructions');

        $plan = $this->create_complete_plan();
        $weekid = $this->add_complete_week($plan->id);
        $this->add_complete_activity($weekid);

        $missing = plan_completeness_checker::missing_required_fields($plan);

        $this->assertContains('Week 1: ' . get_string('details', 'mod_syllabus'), $missing);
        $this->assertContains(
            'Week 1 › Forum kickoff: ' . get_string('studentinstructions', 'mod_syllabus'),
            $missing
        );
    }
}
