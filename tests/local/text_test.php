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
 * Tests that the names the tabs and the notifications show are plain text, escaped once.
 *
 * @package    mod_syllabus
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_syllabus\local;

use advanced_testcase;
use mod_syllabus\output\tab_student_plan;
use mod_syllabus\task\send_workflow_notification;

/**
 * A course or plan named with an ampersand must reach the page escaped once and a plain-text message
 * unescaped: format_string() returns HTML, so a double-mustache tag would print "&amp;amp;".
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_syllabus\local\text
 * @covers     \mod_syllabus\output\tab_student_plan
 * @covers     \mod_syllabus\output\plan_programme_name
 * @covers     \mod_syllabus\task\send_workflow_notification
 */
final class text_test extends advanced_testcase {
    /**
     * Data provider: the two values of formatstringstriptags.
     *
     * @return array
     */
    public static function striptags_provider(): array {
        return ['strip tags on' => [1], 'strip tags off' => [0]];
    }

    /**
     * The plain text does not depend on the site setting.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_plain_returns_the_text_as_typed(int $striptags): void {
        $this->resetAfterTest();
        set_config('formatstringstriptags', $striptags);

        $this->assertSame('Q & A > 3', text::plain('Q & A > 3'));
        $this->assertSame('Q & A', text::plain('<b>Q</b> &amp; A'));
        $this->assertSame('', text::plain(null));
    }

    /**
     * The student tab shows the course name and the programme name escaped once.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_the_student_tab_escapes_the_course_and_programme_names_once(int $striptags): void {
        global $PAGE;

        $this->resetAfterTest();
        set_config('formatstringstriptags', $striptags);
        $category = $this->getDataGenerator()->create_category(['name' => 'Arts & Crafts']);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Q & A > 3', 'category' => $category->id]);
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $this->setAdminUser();

        $output = $PAGE->get_renderer('mod_syllabus');
        $data = (new tab_student_plan($syllabus, $course))->export_for_template($output);
        $html = $output->render_from_template('mod_syllabus/tab_student_plan', $data);

        $this->assertSame('Q & A > 3', $data->disciplinename);
        $this->assertSame('Arts & Crafts', $data->coursename);
        $this->assertStringContainsString('Q &amp; A &gt; 3', $html);
        $this->assertStringContainsString('Arts &amp; Crafts', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * The notification subject and body are plain text, so they carry the plan name as typed.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_the_notification_carries_the_plan_name_unescaped(int $striptags): void {
        global $DB;

        $this->resetAfterTest();
        set_config('formatstringstriptags', $striptags);
        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id, 'name' => 'Q & A > 3']);
        $author = $this->getDataGenerator()->create_user();
        $reviewer = $this->getDataGenerator()->create_user();
        plan_state_manager::submit($syllabus->id, (int) $author->id);
        plan_state_manager::approve($syllabus->id, (int) $reviewer->id);

        $sink = $this->redirectMessages();
        $task = new send_workflow_notification();
        $task->set_custom_data(['type' => 'approved', 'planid' => $syllabus->id]);
        $task->execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Q & A > 3', $messages[0]->subject);
        $this->assertStringContainsString('Q & A > 3', $messages[0]->fullmessage);
        $this->assertStringNotContainsString('&amp;', $messages[0]->subject . $messages[0]->fullmessage);
    }
}
