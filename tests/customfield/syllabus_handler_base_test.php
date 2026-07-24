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
 * Tests for the shared Custom Fields handler behaviour (plan/week/activity areas).
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\customfield;

use advanced_testcase;
use context_module;
use context_system;
use moodle_url;
use stdClass;

/**
 * Tests for the shared handler behaviour, through its three concrete subclasses.
 *
 * plan_handler, week_handler and activity_handler each walk a different number of hops back
 * to the owning syllabus (0, 1 and 2 respectively), so most tests below run once per area to
 * prove the shared base behaves the same regardless of how deep resolve_syllabus_id() reaches.
 *
 * @coversDefaultClass \mod_syllabus\customfield\syllabus_handler_base
 */
final class syllabus_handler_base_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Seeds a course with a syllabus, one week and one activity.
     *
     * @return array{0: stdClass, 1: stdClass, 2: stdClass, 3: stdClass} Course, cm, week and activity records.
     */
    private function seed(): array {
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
        $activityid = $DB->insert_record('syllabus_activities', [
            'weekid'       => $weekid,
            'title'        => 'Kickoff',
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);

        return [$course, $cm, $syllabus, (object) ['weekid' => $weekid, 'activityid' => $activityid]];
    }

    /**
     * Each area's handler paired with the instanceid it owns in seed()'s fixture.
     *
     * @param stdClass $syllabus
     * @param stdClass $ids
     * @return array<string, array{0: \core_customfield\handler, 1: int}>
     */
    private function handlers(stdClass $syllabus, stdClass $ids): array {
        return [
            'plan'     => [plan_handler::create(), $syllabus->id],
            'week'     => [week_handler::create(), $ids->weekid],
            'activity' => [activity_handler::create(), $ids->activityid],
        ];
    }

    /**
     * Each area's handler reports its own component/area, derived by core from the handler's
     * own namespace and class name.
     *
     * @covers \mod_syllabus\customfield\plan_handler
     * @covers \mod_syllabus\customfield\week_handler
     * @covers \mod_syllabus\customfield\activity_handler
     * @return void
     */
    public function test_get_component_and_area(): void {
        [, , $syllabus, $ids] = $this->seed();

        foreach ($this->handlers($syllabus, $ids) as $area => [$handler]) {
            $this->assertSame('mod_syllabus', $handler->get_component());
            $this->assertSame($area, $handler->get_area());
        }
    }

    /**
     * belongs_to_syllabus() confirms membership when the instanceid really belongs to the
     * given syllabus, and rejects both a mismatched syllabus id and an orphaned/unknown
     * instanceid — the cross-instance guard external functions rely on before any write.
     *
     * @covers ::belongs_to_syllabus
     * @return void
     */
    public function test_belongs_to_syllabus(): void {
        [, , $syllabus, $ids] = $this->seed();
        $handlers = $this->handlers($syllabus, $ids);
        $othersyllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course' => $this->getDataGenerator()->create_course()->id,
        ]);

        [$planhandler, $planid] = $handlers['plan'];
        [$weekhandler, $weekid] = $handlers['week'];
        [$activityhandler, $activityid] = $handlers['activity'];

        $this->assertTrue($planhandler->belongs_to_syllabus($planid, $syllabus->id));
        $this->assertTrue($weekhandler->belongs_to_syllabus($weekid, $syllabus->id));
        $this->assertTrue($activityhandler->belongs_to_syllabus($activityid, $syllabus->id));

        $this->assertFalse($planhandler->belongs_to_syllabus($planid, $othersyllabus->id));
        $this->assertFalse($weekhandler->belongs_to_syllabus($weekid, $othersyllabus->id));
        $this->assertFalse($activityhandler->belongs_to_syllabus($activityid, $othersyllabus->id));

        $this->assertFalse($weekhandler->belongs_to_syllabus(999999, $syllabus->id));
        $this->assertFalse($activityhandler->belongs_to_syllabus(999999, $syllabus->id));
    }

    /**
     * The field templates are configured once, site-wide, so the configuration context is
     * always the system context regardless of area.
     *
     * @covers ::get_configuration_context
     * @return void
     */
    public function test_get_configuration_context(): void {
        [, , $syllabus, $ids] = $this->seed();

        foreach ($this->handlers($syllabus, $ids) as [$handler]) {
            $this->assertEquals(context_system::instance()->id, $handler->get_configuration_context()->id);
        }
    }

    /**
     * The configuration URL always points at managefields.php with the matching area param.
     *
     * @covers ::get_configuration_url
     * @return void
     */
    public function test_get_configuration_url(): void {
        [, , $syllabus, $ids] = $this->seed();

        foreach ($this->handlers($syllabus, $ids) as $area => [$handler]) {
            $expected = new moodle_url('/mod/syllabus/managefields.php', ['area' => $area]);
            $this->assertEquals($expected->out(), $handler->get_configuration_url()->out());
        }
    }

    /**
     * get_instance_context() resolves all the way to the activity's own module context for a
     * real instanceid, in every area, no matter how many hops resolve_syllabus_id() takes.
     *
     * @covers ::get_instance_context
     * @return void
     */
    public function test_get_instance_context_resolves_to_module_context(): void {
        [, $cm, $syllabus, $ids] = $this->seed();
        $expected = context_module::instance($cm->id)->id;

        foreach ($this->handlers($syllabus, $ids) as [$handler, $instanceid]) {
            $this->assertEquals($expected, $handler->get_instance_context($instanceid)->id);
        }
    }

    /**
     * get_instance_context() falls back to the system context for instanceid 0 (a form still
     * being built) and for an orphaned/unknown instanceid that resolve_syllabus_id() cannot
     * map back to any syllabus.
     *
     * @covers ::get_instance_context
     * @return void
     */
    public function test_get_instance_context_falls_back_to_system(): void {
        [, , $syllabus, $ids] = $this->seed();
        $handlers = $this->handlers($syllabus, $ids);
        $systemid = context_system::instance()->id;

        foreach ($handlers as [$handler]) {
            $this->assertEquals($systemid, $handler->get_instance_context(0)->id);
        }

        $this->assertEquals($systemid, $handlers['week'][0]->get_instance_context(999999)->id);
        $this->assertEquals($systemid, $handlers['activity'][0]->get_instance_context(999999)->id);
    }

    /**
     * can_configure() is gated on mod/syllabus:review at the system context — held by an
     * admin, not by a plain authenticated user.
     *
     * @covers ::can_configure
     * @return void
     */
    public function test_can_configure(): void {
        [, , $syllabus, $ids] = $this->seed();
        [$handler] = $this->handlers($syllabus, $ids)['plan'];

        $plainuser = $this->getDataGenerator()->create_user();
        $this->setUser($plainuser);
        $this->assertFalse($handler->can_configure());

        $this->setAdminUser();
        $this->assertTrue($handler->can_configure());
    }

    /**
     * can_edit() is gated on mod/syllabus:submit at the instance context — held by the
     * enrolled teacher, not by an enrolled student.
     *
     * @covers ::can_edit
     * @return void
     */
    public function test_can_edit(): void {
        [$course, , $syllabus, $ids] = $this->seed();
        [$handler, $instanceid] = $this->handlers($syllabus, $ids)['plan'];
        $datacontrollers = $handler->get_instance_data($instanceid, true);
        $field = reset($datacontrollers)->get_field();

        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($teacher);
        $this->assertTrue($handler->can_edit($field, $instanceid));

        $this->setUser($student);
        $this->assertFalse($handler->can_edit($field, $instanceid));
    }

    /**
     * can_view() is gated on mod/syllabus:view at the instance context — held by an enrolled
     * student, not by a user with no role in the course at all.
     *
     * @covers ::can_view
     * @return void
     */
    public function test_can_view(): void {
        [$course, , $syllabus, $ids] = $this->seed();
        [$handler, $instanceid] = $this->handlers($syllabus, $ids)['plan'];
        $datacontrollers = $handler->get_instance_data($instanceid, true);
        $field = reset($datacontrollers)->get_field();

        $student = $this->getDataGenerator()->create_user();
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);
        $this->assertTrue($handler->can_view($field, $instanceid));

        $this->setUser($outsider);
        $this->assertFalse($handler->can_view($field, $instanceid));
    }
}
