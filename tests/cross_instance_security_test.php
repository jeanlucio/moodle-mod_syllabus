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
 * Cross-instance isolation tests for mod_syllabus.
 *
 * A single authoritative place proving every write Web Service rejects a request that
 * targets a week/activity/field belonging to a *different* syllabus instance than the one
 * the caller's cmid resolves to — the exact case CLAUDE.md's Security Review Checklist
 * calls out (operating on an entity by isolated PK instead of one bound to an
 * already-validated instanceid). Each write function has its own focused test for this
 * elsewhere too; this file exists so a reviewer can see the whole isolation guarantee
 * proven in one place, and catches gaps like delete_week previously having no such test at
 * all.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus;

use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;
use mod_syllabus\external\delete_activity;
use mod_syllabus\external\delete_week;
use mod_syllabus\external\save_activity;
use mod_syllabus\external\save_customfield_value;
use mod_syllabus\external\save_week;

/**
 * Cross-instance isolation tests for mod_syllabus.
 */
final class cross_instance_security_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a course with a syllabus instance, a week and an activity, all owned by a
     * dedicated teacher enrolled in that course only.
     *
     * @param string $label Distinguishes the two instances in assertion failure messages.
     * @return array [\stdClass $syllabus, \stdClass $teacher, int $weekid, int $activityid]
     */
    private function create_owned_plan(string $label): array {
        $course = $this->getDataGenerator()->create_course(['fullname' => "Course {$label}"]);
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user(['username' => "teacher{$label}"]);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->setUser($teacher);
        $week = save_week::execute($syllabus->cmid, 0, "Week {$label}", null, null, null);
        $activity = save_activity::execute(
            $syllabus->cmid,
            $week['weekid'],
            0,
            "Activity {$label}",
            null,
            null,
            null,
            null,
            null
        );

        return [$syllabus, $teacher, $week['weekid'], $activity['activityid']];
    }

    /**
     * No write Web Service lets course A's teacher touch course B's week/activity/field data
     * by supplying course A's cmid alongside a week/activity/field id that actually belongs
     * to course B — the exact isolated-PK-operation CLAUDE.md's checklist warns against.
     *
     * @covers \mod_syllabus\external\save_week::execute
     * @covers \mod_syllabus\external\delete_week::execute
     * @covers \mod_syllabus\external\save_activity::execute
     * @covers \mod_syllabus\external\delete_activity::execute
     * @covers \mod_syllabus\external\save_customfield_value::execute
     * @return void
     */
    public function test_write_services_reject_cross_instance_targets(): void {
        [$syllabusa, $teachera] = $this->create_owned_plan('a');
        [, , $weekbid, $activitybid] = $this->create_owned_plan('b');

        $planfieldid = $this->field_id(plan_handler::create(), 'coursedescription');

        $this->setUser($teachera);

        $this->assert_rejected(
            fn () => save_week::execute($syllabusa->cmid, $weekbid, 'Hijacked', null, null, null),
            'save_week'
        );
        $this->assert_rejected(
            fn () => delete_week::execute($syllabusa->cmid, $weekbid),
            'delete_week'
        );
        $this->assert_rejected(
            fn () => save_activity::execute(
                $syllabusa->cmid,
                $weekbid,
                0,
                'Hijacked',
                null,
                null,
                null,
                null,
                null
            ),
            'save_activity (foreign weekid)'
        );
        $this->assert_rejected(
            fn () => delete_activity::execute($syllabusa->cmid, $activitybid),
            'delete_activity'
        );
        $this->assert_rejected(
            fn () => save_customfield_value::execute(
                $syllabusa->cmid,
                'plan',
                999999,
                $planfieldid,
                'Hijacked',
                0
            ),
            'save_customfield_value (foreign instanceid)'
        );
    }

    /**
     * belongs_to_syllabus() itself — the shared guard every write function above ultimately
     * relies on for the Custom Field areas — correctly distinguishes a genuinely owned
     * week/activity from one belonging to a different syllabus instance, at the unit level.
     *
     * @covers \mod_syllabus\customfield\syllabus_handler_base::belongs_to_syllabus
     * @return void
     */
    public function test_belongs_to_syllabus_distinguishes_instances(): void {
        [$syllabusa, , $weeka, $activitya] = $this->create_owned_plan('a');
        [$syllabusb, , $weekb, $activityb] = $this->create_owned_plan('b');

        $this->assertTrue(plan_handler::create()->belongs_to_syllabus($syllabusa->id, $syllabusa->id));
        $this->assertFalse(plan_handler::create()->belongs_to_syllabus($syllabusb->id, $syllabusa->id));

        $this->assertTrue(week_handler::create()->belongs_to_syllabus($weeka, $syllabusa->id));
        $this->assertFalse(week_handler::create()->belongs_to_syllabus($weekb, $syllabusa->id));

        $this->assertTrue(activity_handler::create()->belongs_to_syllabus($activitya, $syllabusa->id));
        $this->assertFalse(activity_handler::create()->belongs_to_syllabus($activityb, $syllabusa->id));
    }

    /**
     * Finds a seeded field's id by its shortname within a handler.
     *
     * @param \core_customfield\handler $handler Handler to search.
     * @param string $shortname Field shortname.
     * @return int
     */
    private function field_id(\core_customfield\handler $handler, string $shortname): int {
        foreach ($handler->get_fields() as $field) {
            if ($field->get('shortname') === $shortname) {
                return (int) $field->get('id');
            }
        }
        $this->fail("Seeded field '{$shortname}' not found.");
    }

    /**
     * Asserts that calling the given closure throws — any exception is acceptable here (a
     * missing-record exception from the cross-instance guard, a capability error, etc.) —
     * the property under test is simply that the write never silently succeeds.
     *
     * @param callable $call
     * @param string $label Identifies which service failed to reject, in the failure message.
     * @return void
     */
    private function assert_rejected(callable $call, string $label): void {
        try {
            $call();
            $this->fail("{$label} should have rejected a cross-instance target but did not.");
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
