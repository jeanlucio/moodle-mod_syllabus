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
 * Backup and restore tests for mod_syllabus.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus;

use core_courseformat\local\cmactions;
use mod_syllabus\customfield\activity_handler;
use mod_syllabus\customfield\plan_handler;
use mod_syllabus\customfield\week_handler;

/**
 * Exercises the real backup_controller/restore_controller and duplicate_module() flows —
 * not just the stepslib methods in isolation — since that is the only way to catch a real
 * XML schema mismatch or a missing prepare_activity_structure() call.
 */
final class backup_restore_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Seeds a syllabus instance with one week (with a narrative field value) and one
     * activity within it (also with a narrative field value).
     *
     * @param int $courseid
     * @return array [\stdClass $syllabus, int $weekid, int $activityid]
     */
    private function seed_plan(int $courseid): array {
        global $DB;

        $syllabus = $this->getDataGenerator()->create_module('syllabus', [
            'course'               => $courseid,
            'academicperiod'       => '2026.1',
            'coursestartdate'      => strtotime('2026-06-04'),
            'courseenddate'        => strtotime('2026-07-01'),
            'totalduration'        => 30,
            'presentationvideourl' => 'https://youtu.be/8MZLYHxOdUo',
        ]);

        $now = time();
        $weekid = $DB->insert_record('syllabus_weeks', [
            'syllabusid'   => $syllabus->id,
            'title'        => 'Week 1',
            'duration'     => 90,
            'startdate'    => $now,
            'enddate'      => $now + WEEKSECS,
            'syncdate'     => $now + DAYSECS,
            'synclink'     => 'https://meet.example.org/week1',
            'synctopic'    => 'Kickoff session',
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $activityid = $DB->insert_record('syllabus_activities', [
            'weekid'            => $weekid,
            'title'             => 'Forum kickoff',
            'type'              => 'forum',
            'category'          => 'asynchronous',
            'startdate'         => $now,
            'enddate'           => $now + WEEKSECS,
            'points'            => 10.5,
            'isfinalassessment' => 1,
            'sortorder'         => 0,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);

        $this->set_customfield_value(plan_handler::create(), $syllabus->id, 'coursedescription', 'Plan level content.');
        $this->set_customfield_value(plan_handler::create(), $syllabus->id, 'presentationscript', 'Presentation script content.');
        $this->set_customfield_value(plan_handler::create(), $syllabus->id, 'generalreferences', 'General references content.');
        $this->set_customfield_value(week_handler::create(), $weekid, 'details', 'Week level content.');
        $this->set_customfield_value(activity_handler::create(), $activityid, 'studentinstructions', 'Activity level content.');

        return [$syllabus, $weekid, $activityid];
    }

    /**
     * Sets a Custom Field value directly, mirroring what save_customfield_value::execute()
     * does server-side (avoids depending on the external function layer for test setup).
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
     * Reads a Custom Field's exported (formatted) value for one instance.
     *
     * @param \core_customfield\handler $handler
     * @param int $instanceid
     * @param string $shortname
     * @return string
     */
    private function get_customfield_value(\core_customfield\handler $handler, int $instanceid, string $shortname): string {
        $data = $handler->export_instance_data_object($instanceid);
        return (string) ($data->{$shortname} ?? '');
    }

    /**
     * Backs up the given course and restores it into a brand new course, returning that
     * course. Mirrors mod_playerwords's own full-course backup/restore test pattern.
     *
     * @param \stdClass $course Source course.
     * @return \stdClass The new course the backup was restored into.
     */
    private function backup_and_restore_into_new_course(\stdClass $course): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $admin = get_admin();

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id
        );
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $fp = get_file_packer('application/vnd.moodle.backup');
        $backupfile->extract_to_pathname($fp, make_backup_temp_directory($tempdir));

        $rc = new \restore_controller(
            $tempdir,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourse;
    }

    /**
     * Duplicating an activity within the same course preserves its weeks, activities and
     * every narrative Custom Field value at all three levels — the fast, common case.
     *
     * @covers \restore_syllabus_activity_structure_step::define_structure
     * @return void
     */
    public function test_duplicate_activity_preserves_structure_and_customfields(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        [$syllabus, $weekid, $activityid] = $this->seed_plan($course->id);

        $cm = get_coursemodule_from_instance('syllabus', $syllabus->id, $course->id, false, MUST_EXIST);
        // Core's duplicate_module() is deprecated since Moodle 5.2 (MDL-86858), replaced by
        // cmactions::duplicate() — but that method doesn't exist before 5.2, so this must
        // stay guarded rather than switched outright while the plugin supports 4.5+5.x.
        if (method_exists(cmactions::class, 'duplicate')) {
            $newcm = (new cmactions($course))->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($course, $cm);
        }

        $this->assertNotNull($newcm);
        $this->assertNotSame($cm->id, $newcm->id);

        $newweek = $DB->get_record('syllabus_weeks', ['syllabusid' => $newcm->instance], '*', MUST_EXIST);
        $this->assertNotEquals($weekid, $newweek->id, 'The week must get a new id, not reuse the original.');
        $this->assertSame('Week 1', $newweek->title);
        $this->assertEquals(90, $newweek->duration);

        $newactivity = $DB->get_record('syllabus_activities', ['weekid' => $newweek->id], '*', MUST_EXIST);
        $this->assertNotEquals($activityid, $newactivity->id);
        $this->assertSame('Forum kickoff', $newactivity->title);
        $this->assertEquals(10.5, $newactivity->points);

        $this->assertStringContainsString(
            'Plan level content.',
            $this->get_customfield_value(plan_handler::create(), $newcm->instance, 'coursedescription')
        );
        $this->assertStringContainsString(
            'Week level content.',
            $this->get_customfield_value(week_handler::create(), $newweek->id, 'details')
        );
        $this->assertStringContainsString(
            'Activity level content.',
            $this->get_customfield_value(activity_handler::create(), $newactivity->id, 'studentinstructions')
        );
    }

    /**
     * A full course backup restored into a brand new course preserves the same structure and
     * content as duplication, exercising the real backup_controller/restore_controller flow
     * end to end rather than just the same-course duplicate_module() shortcut.
     *
     * @covers \backup_syllabus_activity_structure_step::define_structure
     * @covers \restore_syllabus_activity_structure_step::define_structure
     * @return void
     */
    public function test_full_course_backup_restore_preserves_structure_and_customfields(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->seed_plan($course->id);

        $newcourse = $this->backup_and_restore_into_new_course($course);

        $newsyllabus = $DB->get_record('syllabus', ['course' => $newcourse->id], '*', MUST_EXIST);
        $newweek = $DB->get_record('syllabus_weeks', ['syllabusid' => $newsyllabus->id], '*', MUST_EXIST);
        $newactivity = $DB->get_record('syllabus_activities', ['weekid' => $newweek->id], '*', MUST_EXIST);

        $this->assertSame('Week 1', $newweek->title);
        $this->assertSame('Forum kickoff', $newactivity->title);
        $this->assertStringContainsString(
            'Plan level content.',
            $this->get_customfield_value(plan_handler::create(), $newsyllabus->id, 'coursedescription')
        );
        $this->assertStringContainsString(
            'Week level content.',
            $this->get_customfield_value(week_handler::create(), $newweek->id, 'details')
        );
        $this->assertStringContainsString(
            'Activity level content.',
            $this->get_customfield_value(activity_handler::create(), $newactivity->id, 'studentinstructions')
        );

        // Characterisation/Presentation fields (syllabus columns), the synchronous session
        // fields (syllabus_weeks columns), the final assessment flag (syllabus_activities
        // column) and the two plan-level narrative Custom Fields all survive a real
        // backup/restore.
        $this->assertSame('2026.1', $newsyllabus->academicperiod);
        $this->assertEquals(30, $newsyllabus->totalduration);
        $this->assertSame('https://youtu.be/8MZLYHxOdUo', $newsyllabus->presentationvideourl);
        $this->assertSame('https://meet.example.org/week1', $newweek->synclink);
        $this->assertSame('Kickoff session', $newweek->synctopic);
        $this->assertNotEmpty($newweek->syncdate);
        $this->assertEquals(1, $newactivity->isfinalassessment);
        $this->assertStringContainsString(
            'Presentation script content.',
            $this->get_customfield_value(plan_handler::create(), $newsyllabus->id, 'presentationscript')
        );
        $this->assertStringContainsString(
            'General references content.',
            $this->get_customfield_value(plan_handler::create(), $newsyllabus->id, 'generalreferences')
        );
    }

    /**
     * submittedby/reviewedby/unpublishedby are remapped to the restored copies of the same
     * users when a full course backup carries user info along (the default when both the
     * source and target enrol/include the same users).
     *
     * @covers \restore_syllabus_activity_structure_step::process_syllabus
     * @return void
     */
    public function test_full_course_backup_restore_remaps_workflow_users(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $submitter = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($submitter->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($reviewer->id, $course->id, 'manager');

        [$syllabus] = $this->seed_plan($course->id);
        \mod_syllabus\local\plan_state_manager::submit($syllabus->id, (int) $submitter->id);
        \mod_syllabus\local\plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        $newcourse = $this->backup_and_restore_into_new_course($course);

        $newsyllabus = $DB->get_record('syllabus', ['course' => $newcourse->id], '*', MUST_EXIST);
        $newsubmitter = $DB->get_record('user', ['id' => $newsyllabus->submittedby], '*', MUST_EXIST);
        $newreviewer = $DB->get_record('user', ['id' => $newsyllabus->reviewedby], '*', MUST_EXIST);

        // Matched back to the original by username rather than asserting a specific id
        // relationship: same-site restores may map onto the same existing account rather
        // than creating a new one, but either way the reference must resolve to the right
        // person, not be left pointing at whatever the old numeric id happens to mean here.
        $this->assertSame($submitter->username, $newsubmitter->username);
        $this->assertSame($reviewer->username, $newreviewer->username);
    }
}
