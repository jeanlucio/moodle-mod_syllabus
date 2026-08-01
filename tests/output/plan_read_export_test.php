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
 * Tests for plan_read_export::final_assessment().
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use advanced_testcase;
use mod_syllabus\customfield\plan_handler;

/**
 * Tests for plan_read_export::final_assessment().
 *
 * @covers \mod_syllabus\output\plan_read_export
 */
final class plan_read_export_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
     * The plan-level Final assessment fields (title/type/dates/points) and its narrative
     * Custom Field value are exported as a single object, structurally paralleling
     * Characterisation rather than an activity inside some week.
     *
     * @return void
     */
    public function test_final_assessment_exports_plan_level_fields(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course'                   => $course->id,
            'finalassessmenttitle'     => 'Final exam',
            'finalassessmenttype'      => 'quiz',
            'finalassessmentstartdate' => 1749000000,
            'finalassessmentenddate'   => 1749600000,
            'finalassessmentpoints'    => 100,
        ]);
        $syllabus = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->set_customfield_value(
            plan_handler::create(),
            $syllabus->id,
            'finalassessmentinstructions',
            'Exam covering the whole semester.'
        );

        $reader = new plan_reader($syllabus);
        $narrative = $reader->plan_narrative();
        $exported = plan_read_export::final_assessment($syllabus, $narrative);

        $this->assertSame('Final exam', $exported->title);
        $this->assertSame('quiz', $exported->type);
        $this->assertEquals(1749000000, $exported->startdate);
        $this->assertEquals(1749600000, $exported->enddate);
        $this->assertEquals(100, $exported->points);
        $this->assertStringContainsString('Exam covering the whole semester.', $exported->instructions);
    }

    /**
     * A plan that never had its Final assessment filled in exports an empty title, the signal
     * the read-only templates use to decide whether to render the block at all.
     *
     * @return void
     */
    public function test_final_assessment_title_is_empty_when_never_filled_in(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course' => $course->id,
            'finalassessmenttitle' => '',
        ]);

        $reader = new plan_reader($syllabus);
        $narrative = $reader->plan_narrative();
        $exported = plan_read_export::final_assessment($syllabus, $narrative);

        $this->assertSame('', trim((string) $exported->title));
    }

    /**
     * final_assessment() only attaches instructionsreviewnote when includereviewnotes is true
     * — the tutor/student tabs never pass it, so their exported object never carries it at all
     * (rather than carrying a permanently-null property that would invite a template to
     * accidentally render an editable box for a role that must never see one).
     *
     * @return void
     */
    public function test_final_assessment_review_note_only_attached_when_requested(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course'               => $course->id,
            'finalassessmenttitle' => 'Final exam',
        ]);
        $syllabus = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);

        $reader = new plan_reader($syllabus);
        $narrative = $reader->plan_narrative();

        $planfielddata = plan_handler::create()->get_instance_data($syllabus->id, true);
        $finalassessmentfielddata = [];
        foreach ($planfielddata as $fieldid => $datacontroller) {
            if ($datacontroller->get_field()->get('shortname') === 'finalassessmentinstructions') {
                $finalassessmentfielddata = [$fieldid => $datacontroller];
                break;
            }
        }
        $reviewnotes = [
            "plan:{$syllabus->id}:" . array_key_first($finalassessmentfielddata) => 'Please clarify the rubric.',
        ];

        $withoutnotes = plan_read_export::final_assessment($syllabus, $narrative);
        $this->assertFalse(property_exists($withoutnotes, 'instructionsreviewnote'));

        $withnotes = plan_read_export::final_assessment(
            $syllabus,
            $narrative,
            true,
            $reader,
            $finalassessmentfielddata,
            $reviewnotes
        );
        $this->assertNotNull($withnotes->instructionsreviewnote);
        $this->assertSame('Please clarify the rubric.', $withnotes->instructionsreviewnote->coordinatornote);
    }

    /**
     * weeks() only attaches each field's ...reviewnote object when includereviewnotes is true,
     * propagating the same flag down into activities() for its own narrative fields.
     *
     * @return void
     */
    public function test_weeks_review_note_only_attached_when_requested(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
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

        $reader = new plan_reader($syllabus);
        $weeks = $reader->weeks();

        $withoutnotes = plan_read_export::weeks($reader, $weeks, true);
        $this->assertFalse(property_exists($withoutnotes[0], 'detailsreviewnote'));
        $this->assertFalse(property_exists($withoutnotes[0]->activities[0], 'studentinstructionsreviewnote'));

        $withnotes = plan_read_export::weeks($reader, $weeks, true, true, []);
        $this->assertNotNull($withnotes[0]->detailsreviewnote);
        $this->assertSame('', $withnotes[0]->detailsreviewnote->coordinatornote);
        $this->assertNotNull($withnotes[0]->activities[0]->studentinstructionsreviewnote);
        $this->assertSame('', $withnotes[0]->activities[0]->studentinstructionsreviewnote->coordinatornote);
    }

    /**
     * Seeds a course with a syllabus, one week and one activity — same fixture shape as
     * test_weeks_review_note_only_attached_when_requested, reused by the change-indicator
     * tests below.
     *
     * @return array{0: \stdClass, 1: int, 2: int} Syllabus, weekid, activityid.
     */
    private function seed_week_and_activity(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $now = time();
        $weekid = (int) $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id, 'title' => 'Week 1', 'duration' => 8,
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $activityid = (int) $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'Kickoff forum', 'type' => 'forum', 'category' => 'synchronous',
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        return [$syllabus, $weekid, $activityid];
    }

    /**
     * weeks() only attaches isnew/...changed/structurechanged when $changediff is passed —
     * same "not even a null property" absence tab_visibility_test.php's reviewnote sibling
     * already established, so a template can never accidentally render a badge for a role
     * that never gets this parameter at all (tutor/student).
     *
     * @return void
     */
    public function test_weeks_change_indicators_only_attached_when_requested(): void {
        [$syllabus, ] = $this->seed_week_and_activity();
        $reader = new plan_reader($syllabus);
        $weeks = $reader->weeks();

        $withoutdiff = plan_read_export::weeks($reader, $weeks, true);
        $this->assertFalse(property_exists($withoutdiff[0], 'isnew'));
        $this->assertFalse(property_exists($withoutdiff[0], 'detailschanged'));
        $this->assertFalse(property_exists($withoutdiff[0]->activities[0], 'isnew'));

        $emptydiff = ['planfields' => [], 'weeks' => [], 'activities' => [], 'newweekids' => [], 'newactivityids' => []];
        $withdiff = plan_read_export::weeks($reader, $weeks, true, false, [], $emptydiff);
        $this->assertFalse($withdiff[0]->isnew);
        $this->assertFalse($withdiff[0]->detailschanged);
    }

    /**
     * A week whose id is in newweekids exports isnew true; a week with changed keys reported
     * against its own id exports the matching narrative ...changed flags, and structurechanged
     * true only when at least one of those keys is a structural column, not a narrative
     * shortname — 'duration' counts, 'details' alone does not.
     *
     * @return void
     */
    public function test_weeks_change_indicators_reflect_the_diff(): void {
        [$syllabus, $weekid, ] = $this->seed_week_and_activity();
        $reader = new plan_reader($syllabus);
        $weeks = $reader->weeks();

        $diff = [
            'planfields' => [], 'activities' => [], 'newactivityids' => [],
            'newweekids' => [], 'weeks' => [$weekid => ['details', 'duration']],
        ];
        $exported = plan_read_export::weeks($reader, $weeks, true, false, [], $diff);
        $this->assertFalse($exported[0]->isnew);
        $this->assertTrue($exported[0]->detailschanged);
        $this->assertFalse($exported[0]->supportmaterialchanged);
        $this->assertTrue($exported[0]->structurechanged);

        $newweekdiff = [
            'planfields' => [], 'activities' => [], 'newactivityids' => [],
            'newweekids' => [$weekid], 'weeks' => [],
        ];
        $newexported = plan_read_export::weeks($reader, $weeks, true, false, [], $newweekdiff);
        $this->assertTrue($newexported[0]->isnew);
    }

    /**
     * Same rationale as test_weeks_change_indicators_reflect_the_diff, one level down: an
     * activity's own id in newactivityids, changed narrative fields (including the
     * showtutorfields-gated gradingcriteria/tutorguidance), and structurechanged from a
     * structural-only key like 'points'.
     *
     * @return void
     */
    public function test_activities_change_indicators_reflect_the_diff(): void {
        [$syllabus, $weekid, $activityid] = $this->seed_week_and_activity();
        $reader = new plan_reader($syllabus);
        $weeks = $reader->weeks();

        $diff = [
            'planfields' => [], 'newweekids' => [], 'weeks' => [],
            'newactivityids' => [],
            'activities' => [$activityid => ['gradingcriteria', 'points']],
        ];
        $exported = plan_read_export::weeks($reader, $weeks, true, false, [], $diff);
        $activity = $exported[0]->activities[0];
        $this->assertFalse($activity->isnew);
        $this->assertTrue($activity->gradingcriteriachanged);
        $this->assertFalse($activity->tutorguidancechanged);
        $this->assertFalse($activity->studentinstructionschanged);
        $this->assertTrue($activity->structurechanged);

        $newactivitydiff = [
            'planfields' => [], 'newweekids' => [], 'weeks' => [],
            'newactivityids' => [$activityid], 'activities' => [],
        ];
        $withnew = plan_read_export::weeks($reader, $weeks, true, false, [], $newactivitydiff);
        $this->assertTrue($withnew[0]->activities[0]->isnew);
    }

    /**
     * final_assessment() splits the same flat 'planfields' list into instructionschanged (the
     * block's own narrative field) versus structurechanged (any of its structural columns),
     * only when $changediff is passed at all.
     *
     * @return void
     */
    public function test_final_assessment_change_indicators_reflect_the_diff(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $reader = new plan_reader($syllabus);
        $narrative = $reader->plan_narrative();

        $withoutdiff = plan_read_export::final_assessment($syllabus, $narrative);
        $this->assertFalse(property_exists($withoutdiff, 'instructionschanged'));

        $diff = ['planfields' => ['finalassessmentinstructions']];
        $instructionschanged = plan_read_export::final_assessment($syllabus, $narrative, false, null, [], [], $diff);
        $this->assertTrue($instructionschanged->instructionschanged);
        $this->assertFalse($instructionschanged->structurechanged);

        $structuraldiff = ['planfields' => ['finalassessmenttitle']];
        $structurechanged = plan_read_export::final_assessment($syllabus, $narrative, false, null, [], [], $structuraldiff);
        $this->assertFalse($structurechanged->instructionschanged);
        $this->assertTrue($structurechanged->structurechanged);
    }
}
