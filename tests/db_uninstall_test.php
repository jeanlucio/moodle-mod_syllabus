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

namespace mod_syllabus;

use advanced_testcase;

/**
 * Unit tests for the mod_syllabus pre-uninstallation hook.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_syllabus_uninstall
 */
final class db_uninstall_test extends advanced_testcase {
    /**
     * Resets the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../db/install.php');
        require_once(__DIR__ . '/../db/uninstall.php');
    }

    /**
     * Counts customfield categories currently registered for mod_syllabus.
     *
     * @return int
     */
    private function count_categories(): int {
        global $DB;

        return $DB->count_records('customfield_category', ['component' => 'mod_syllabus']);
    }

    /**
     * xmldb_syllabus_uninstall() removes every customfield category/field/data row the
     * plugin's own install seeded, across all three areas — not just the plugin's own
     * install.xml tables, which core already drops on its own regardless of this hook.
     *
     * This is the regression test for a real bug found live: reinstalling the plugin
     * without this hook left the previous run's categories/fields orphaned in core tables,
     * so every narrative field showed up duplicated once per past reinstall.
     *
     * @return void
     */
    public function test_uninstall_removes_all_seeded_customfield_categories(): void {
        global $DB;

        // The plugin's own install already seeded one set; simulate what a *second* install
        // would leave behind (the exact scenario that produced the live bug) by seeding again.
        $this->assertGreaterThan(0, $this->count_categories(), 'Sanity check: install.php seeded categories.');
        xmldb_syllabus_install();
        $this->assertGreaterThan(3, $this->count_categories(), 'Sanity check: a second install duplicated them.');

        xmldb_syllabus_uninstall();

        $this->assertSame(0, $this->count_categories());
        $this->assertSame(0, $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'mod_syllabus'"
        ));
        $this->assertSame(0, $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {customfield_data} d
               JOIN {customfield_field} f ON f.id = d.fieldid
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'mod_syllabus'"
        ));
    }
}
