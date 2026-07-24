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
 * Tests for plan_read_export::final_assessment_activities(), not exercised by
 * tab_visibility_test.php's fixtures (none of which ever flag an activity isfinalassessment).
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\output;

use advanced_testcase;

/**
 * Tests for plan_read_export::final_assessment_activities().
 *
 * @coversDefaultClass \mod_syllabus\output\plan_read_export
 */
final class plan_read_export_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Only activities flagged isfinalassessment are pulled out, each carrying its own week's
     * title for context; a regular activity in the same week is left out.
     *
     * @covers ::final_assessment_activities
     * @return void
     */
    public function test_final_assessment_activities_filters_and_tags_week_title(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $now = time();
        $weekid = $DB->insert_record('syllabus_weeks', [
            'syllabusid'   => $syllabus->id,
            'title'        => 'Final week',
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'Regular quiz', 'isfinalassessment' => 0,
            'sortorder' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'Final exam', 'isfinalassessment' => 1,
            'sortorder' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $reader = new plan_reader($syllabus);
        $weeks = $reader->weeks();
        $exportedweeks = plan_read_export::weeks($reader, $weeks, true);
        $finalassessments = plan_read_export::final_assessment_activities($exportedweeks);

        $this->assertCount(1, $finalassessments);
        $this->assertSame('Final exam', $finalassessments[0]->title);
        $this->assertSame('Final week', $finalassessments[0]->weektitle);
    }

    /**
     * A week with no final-assessment activities contributes nothing to the result.
     *
     * @covers ::final_assessment_activities
     * @return void
     */
    public function test_final_assessment_activities_returns_empty_when_none_flagged(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $now = time();
        $weekid = $DB->insert_record('syllabus_weeks', [
            'syllabusid'   => $syllabus->id,
            'title'        => 'Week 1',
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'Regular quiz', 'isfinalassessment' => 0,
            'sortorder' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $reader = new plan_reader($syllabus);
        $weeks = $reader->weeks();
        $exportedweeks = plan_read_export::weeks($reader, $weeks, false);

        $this->assertSame([], plan_read_export::final_assessment_activities($exportedweeks));
        $this->assertFalse(property_exists($exportedweeks[0]->activities[0], 'gradingcriteria'));
    }
}
