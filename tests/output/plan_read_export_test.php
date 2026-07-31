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
}
