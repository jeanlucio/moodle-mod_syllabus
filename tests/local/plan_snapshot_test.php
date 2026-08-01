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
 * Tests for plan_snapshot.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\local;

use advanced_testcase;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;
use mod_syllabus\output\plan_reader;

/**
 * Tests for plan_snapshot's build()/diff() pair, the basis for showing the coordinator what
 * changed on a plan since their last decision.
 *
 * @covers \mod_syllabus\local\plan_snapshot
 */
final class plan_snapshot_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Sets a Custom Field value directly, mirroring save_customfield_value::execute() — same
     * helper already used by plan_reader_test.php and plan_read_export_test.php.
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
     * Builds a snapshot straight from a syllabus id — the same three calls
     * plan_state_manager::snapshot_json() makes internally, exposed here so tests can build a
     * snapshot without duplicating that wiring.
     *
     * @param \stdClass $syllabus
     * @return array
     */
    private function build_snapshot(\stdClass $syllabus): array {
        $reader = new plan_reader($syllabus);
        return plan_snapshot::build($syllabus, $reader->plan_narrative(), $reader->weeks(), $reader);
    }

    /**
     * build() captures both structural columns and narrative Custom Field content, nested by
     * week and activity id.
     *
     * @return void
     */
    public function test_build_captures_structural_and_narrative_fields(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course' => $course->id,
            'academicperiod' => '2026.1',
        ]);
        $this->set_customfield_value(plan_handler::create(), $syllabus->id, 'coursedescription', 'A description.');

        $now = time();
        $weekid = (int) $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id, 'title' => 'Week 1', 'duration' => 8,
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->set_customfield_value(week_handler::create(), $weekid, 'details', 'Week details.');
        $activityid = (int) $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'Forum 1', 'type' => 'forum', 'category' => 'online',
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        $syllabus = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $snapshot = $this->build_snapshot($syllabus);

        $this->assertSame('2026.1', $snapshot['plan']['academicperiod']);
        $this->assertSame('A description.', $snapshot['plan']['coursedescription']);
        $this->assertSame('Week 1', $snapshot['weeks'][$weekid]['title']);
        $this->assertSame('Week details.', $snapshot['weeks'][$weekid]['details']);
        $this->assertSame('Forum 1', $snapshot['weeks'][$weekid]['activities'][$activityid]['title']);
    }

    /**
     * diff() reports nothing at all when the two snapshots are identical.
     *
     * @return void
     */
    public function test_diff_is_empty_when_nothing_changed(): void {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $syllabus = $this->refresh($syllabus->id);

        $snapshot = $this->build_snapshot($syllabus);
        $diff = plan_snapshot::diff($snapshot, $snapshot);

        $this->assertSame([], $diff['planfields']);
        $this->assertSame([], $diff['weeks']);
        $this->assertSame([], $diff['activities']);
        $this->assertSame([], $diff['newweekids']);
        $this->assertSame([], $diff['removedweektitles']);
        $this->assertSame([], $diff['newactivityids']);
        $this->assertSame([], $diff['removedactivitylabels']);
    }

    /**
     * A changed plan-level narrative field and a changed plan-level structural column are both
     * reported under 'planfields' — the diff does not distinguish the two kinds at this level,
     * that classification happens on the consuming side (tab_full_plan.php).
     *
     * @return void
     */
    public function test_diff_detects_plan_level_changes(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course' => $course->id,
            'academicperiod' => '2026.1',
        ]);
        $syllabus = $this->refresh($syllabus->id);
        $old = $this->build_snapshot($syllabus);

        $this->set_customfield_value(plan_handler::create(), $syllabus->id, 'coursedescription', 'Updated.');
        $DB->set_field('syllabus', 'academicperiod', '2026.2', ['id' => $syllabus->id]);
        $syllabus = $this->refresh($syllabus->id);
        $new = $this->build_snapshot($syllabus);

        $diff = plan_snapshot::diff($old, $new);
        $this->assertContains('coursedescription', $diff['planfields']);
        $this->assertContains('academicperiod', $diff['planfields']);
    }

    /**
     * A changed week field (structural or narrative) is reported under the week's own id, and
     * a changed activity field under the activity's own id, nested inside that week.
     *
     * @return void
     */
    public function test_diff_detects_week_and_activity_changes(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $syllabus = $this->refresh($syllabus->id);
        $now = time();
        $weekid = (int) $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id, 'title' => 'Week 1', 'duration' => 8,
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $activityid = (int) $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'Forum 1', 'type' => 'forum', 'category' => 'online',
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $old = $this->build_snapshot($syllabus);

        $DB->set_field('syllabus_weeks', 'duration', 10, ['id' => $weekid]);
        $DB->set_field('syllabus_activities', 'points', 50, ['id' => $activityid]);
        $new = $this->build_snapshot($syllabus);

        $diff = plan_snapshot::diff($old, $new);
        $this->assertContains('duration', $diff['weeks'][$weekid]);
        $this->assertContains('points', $diff['activities'][$activityid]);
    }

    /**
     * A week added since the old snapshot is reported in newweekids, with no entry of its own
     * in the 'weeks' diff bucket (there is nothing to compare it against).
     *
     * @return void
     */
    public function test_diff_detects_a_new_week(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $syllabus = $this->refresh($syllabus->id);
        $old = $this->build_snapshot($syllabus);

        $now = time();
        $weekid = (int) $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id, 'title' => 'Week 1', 'duration' => 8,
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $new = $this->build_snapshot($syllabus);

        $diff = plan_snapshot::diff($old, $new);
        $this->assertContains($weekid, $diff['newweekids']);
        $this->assertArrayNotHasKey($weekid, $diff['weeks']);
    }

    /**
     * A week present in the old snapshot but deleted since is reported by its title in
     * removedweektitles — there is no id-based row left to attach an indicator to.
     *
     * @return void
     */
    public function test_diff_detects_a_removed_week(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $syllabus = $this->refresh($syllabus->id);
        $now = time();
        $weekid = (int) $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id, 'title' => 'Week to delete', 'duration' => 8,
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $old = $this->build_snapshot($syllabus);

        $DB->delete_records('syllabus_weeks', ['id' => $weekid]);
        $new = $this->build_snapshot($syllabus);

        $diff = plan_snapshot::diff($old, $new);
        $this->assertContains('Week to delete', $diff['removedweektitles']);
    }

    /**
     * A new/removed activity within a week that itself is unchanged is reported by
     * newactivityids/removedactivitylabels — the latter prefixed with the owning week's title.
     *
     * @return void
     */
    public function test_diff_detects_new_and_removed_activities(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $syllabus = $this->refresh($syllabus->id);
        $now = time();
        $weekid = (int) $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id, 'title' => 'Week 1', 'duration' => 8,
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $toremoveid = (int) $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'Old activity', 'type' => 'forum', 'category' => 'online',
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $old = $this->build_snapshot($syllabus);

        $DB->delete_records('syllabus_activities', ['id' => $toremoveid]);
        $newactivityid = (int) $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'New activity', 'type' => 'forum', 'category' => 'online',
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $new = $this->build_snapshot($syllabus);

        $diff = plan_snapshot::diff($old, $new);
        $this->assertContains($newactivityid, $diff['newactivityids']);
        $this->assertContains('Week 1 › Old activity', $diff['removedactivitylabels']);
    }

    /**
     * Re-fetches a syllabus record fresh from the database.
     *
     * @param int $syllabusid
     * @return \stdClass
     */
    private function refresh(int $syllabusid): \stdClass {
        global $DB;

        return $DB->get_record('syllabus', ['id' => $syllabusid], '*', MUST_EXIST);
    }
}
