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
 * Unit tests for the mod_syllabus upgrade steps.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_syllabus_upgrade
 */
final class db_upgrade_test extends advanced_testcase {
    /**
     * Resets the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        // The upgrade_mod_savepoint() helper lives in upgradelib.php, only autoloaded by the
        // real upgrade runner — calling the upgrade function directly in a test needs it
        // pulled in by hand.
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
    }

    /**
     * A site that installed the plugin before the "<shortname>help" lang strings existed never
     * had its narrative Custom Field descriptions seeded — this is the regression test for
     * that real gap, found live: coursedescription's description cleared (simulating a
     * pre-existing site) is backfilled from the current lang string once the upgrade step runs.
     *
     * @return void
     */
    public function test_upgrade_backfills_missing_short_descriptions(): void {
        global $DB;

        $field = $DB->get_record('customfield_field', ['shortname' => 'coursedescription'], '*', MUST_EXIST);
        $DB->set_field('customfield_field', 'description', '', ['id' => $field->id]);

        // The savepoint helper refuses to record a version that is not strictly newer than the
        // plugin's currently recorded one — lower it first, exactly as it would be on a real
        // site partway through an upgrade, so the call below is a genuine step forward instead
        // of a no-op "downgrade".
        set_config('version', 2026072509, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072509);

        $updated = $DB->get_record('customfield_field', ['id' => $field->id], '*', MUST_EXIST);
        $this->assertSame(get_string('coursedescriptionhelp', 'mod_syllabus'), $updated->description);
        $this->assertEquals(FORMAT_HTML, $updated->descriptionformat);
    }

    /**
     * A field an institution already customised via managefields.php (a non-empty
     * description) is left exactly as it is — the upgrade step only fills in gaps, it never
     * overwrites a deliberate customisation.
     *
     * @return void
     */
    public function test_upgrade_does_not_overwrite_a_customised_description(): void {
        global $DB;

        $field = $DB->get_record('customfield_field', ['shortname' => 'objectives'], '*', MUST_EXIST);
        $DB->set_field('customfield_field', 'description', 'Custom institutional wording.', ['id' => $field->id]);

        set_config('version', 2026072509, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072509);

        $updated = $DB->get_record('customfield_field', ['id' => $field->id], '*', MUST_EXIST);
        $this->assertSame('Custom institutional wording.', $updated->description);
    }
}
