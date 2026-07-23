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

namespace mod_syllabus\external;

use advanced_testcase;
use coding_exception;
use core_external\external_api;
use invalid_parameter_exception;
use mod_syllabus\local\plan_state_manager;

/**
 * Unit tests for the mod_syllabus_review_plan external function.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_syllabus\external\review_plan
 */
final class review_plan_test extends advanced_testcase {
    /**
     * Resets the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a course with a syllabus instance already submitted for review.
     *
     * @return array [\stdClass $syllabus, \stdClass $teacher, \stdClass $manager]
     */
    private function create_submitted_plan(): array {
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, 'manager');

        $this->setUser($teacher);
        submit_plan::execute($syllabus->cmid);

        return [$syllabus, $teacher, $manager];
    }

    /**
     * A coordinator can approve a submitted plan, notifying the author.
     *
     * @return void
     */
    public function test_approves_and_notifies_author(): void {
        global $DB;

        [$syllabus, $teacher, $manager] = $this->create_submitted_plan();

        $this->setUser($manager);
        $sink = $this->redirectMessages();
        $result = review_plan::execute($syllabus->cmid, 'approved');
        $result = external_api::clean_returnvalue(review_plan::execute_returns(), $result);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertTrue($result['success']);
        $this->assertSame(plan_state_manager::STATUS_APPROVED, $result['status']);
        $this->assertSame(
            plan_state_manager::STATUS_APPROVED,
            $DB->get_field('syllabus', 'status', ['id' => $syllabus->id])
        );

        $this->assertCount(1, $messages);
        $this->assertEquals($teacher->id, reset($messages)->useridto);
    }

    /**
     * A coordinator can request changes, with the reason reaching the author's message.
     *
     * @return void
     */
    public function test_requests_changes_and_notifies_author(): void {
        global $DB;

        [$syllabus, $teacher, $manager] = $this->create_submitted_plan();

        $this->setUser($manager);
        $sink = $this->redirectMessages();
        review_plan::execute($syllabus->cmid, 'changes_requested', 'Fix the bibliography.');
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertSame(
            plan_state_manager::STATUS_CHANGES_REQUESTED,
            $DB->get_field('syllabus', 'status', ['id' => $syllabus->id])
        );
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($teacher->id, $message->useridto);
        $this->assertStringContainsString('Fix the bibliography.', $message->fullmessage);
    }

    /**
     * Requesting changes without a reason is rejected.
     *
     * @return void
     */
    public function test_requests_changes_requires_reason(): void {
        [$syllabus, , $manager] = $this->create_submitted_plan();
        $this->setUser($manager);

        $this->expectException(invalid_parameter_exception::class);
        review_plan::execute($syllabus->cmid, 'changes_requested', '');
    }

    /**
     * An invalid decision value is rejected.
     *
     * @return void
     */
    public function test_invalid_decision_rejected(): void {
        [$syllabus, , $manager] = $this->create_submitted_plan();
        $this->setUser($manager);

        $this->expectException(invalid_parameter_exception::class);
        review_plan::execute($syllabus->cmid, 'bogus');
    }

    /**
     * A reviewer cannot approve their own submission — the state machine's own rule surfaces
     * through the external function unchanged.
     *
     * @return void
     */
    public function test_cannot_approve_own_submission(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $teachermanager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teachermanager->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teachermanager->id, $course->id, 'manager');

        $this->setUser($teachermanager);
        submit_plan::execute($syllabus->cmid);

        $this->expectException(coding_exception::class);
        review_plan::execute($syllabus->cmid, 'approved');
    }

    /**
     * A user without mod/syllabus:review cannot review a plan.
     *
     * @return void
     */
    public function test_requires_capability(): void {
        [$syllabus, $teacher] = $this->create_submitted_plan();
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        review_plan::execute($syllabus->cmid, 'approved');
    }
}
