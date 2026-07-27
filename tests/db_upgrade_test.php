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
 * @covers \mod_syllabus\local\customfield_seeder
 * @covers \mod_syllabus\local\help_text_builder
 * @covers \mod_syllabus\customfield\help_handler
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
     * A site that installed the plugin before the "<shortname>help"/"<shortname>helpfull" lang
     * strings existed never had its narrative Custom Field descriptions seeded — this is the
     * regression test for that real gap, found live: coursedescription's description cleared
     * (simulating a pre-existing site) ends up with the current combined summary + full model
     * guidance once both upgrade steps run.
     *
     * @return void
     */
    public function test_upgrade_backfills_missing_descriptions(): void {
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
        $this->assertSame(\mod_syllabus\local\help_text_builder::build('coursedescription'), $updated->description);
        $this->assertEquals(FORMAT_HTML, $updated->descriptionformat);
    }

    /**
     * A field an institution already customised via managefields.php (a non-empty description
     * that is neither the short-only default nor blank) is left exactly as it is by both
     * steps — the upgrade only fills in gaps, it never overwrites a deliberate customisation.
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

    /**
     * A site that installed the plugin before the 'help' Custom Field area existed never got
     * it (there was nothing to seed it — db/install.php only runs once, at initial install).
     * The upgrade step seeds it from scratch, exactly as a fresh install would.
     *
     * @return void
     */
    public function test_upgrade_seeds_missing_help_area(): void {
        global $DB;

        // A fresh PHPUnit install already has the 'help' area (db/install.php seeds all four
        // areas today) — delete it to simulate a site that predates it.
        \mod_syllabus\customfield\help_handler::create()->delete_all();
        $this->assertFalse(
            $DB->record_exists('customfield_category', ['component' => 'mod_syllabus', 'area' => 'help'])
        );

        set_config('version', 2026072509, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072509);

        $this->assertTrue(
            $DB->record_exists('customfield_category', ['component' => 'mod_syllabus', 'area' => 'help'])
        );
        $field = $DB->get_record('customfield_field', ['shortname' => 'characterisation'], '*', MUST_EXIST);
        $this->assertSame(\mod_syllabus\local\help_text_builder::build('characterisation'), $field->description);
    }

    /**
     * Builds the exact <details>/<summary>-based HTML the previous version of this upgrade
     * step wrote for a given shortname, before the fix — used by the two tests below to
     * reconstruct the two possible broken states a real site could be left in.
     *
     * @param string $shortname
     * @return string
     */
    private function broken_details_description(string $shortname): string {
        return \html_writer::tag('p', get_string($shortname . 'help', 'mod_syllabus')) .
            \html_writer::tag(
                'details',
                \html_writer::tag('summary', get_string('viewmodelguidance', 'mod_syllabus')) .
                    \html_writer::tag('p', get_string($shortname . 'helpfull', 'mod_syllabus')),
                ['class' => 'syllabus-field-help-full']
            );
    }

    /**
     * Regression test for the second real bug found live: the previous version of this
     * upgrade step wrote straight to the database, bypassing the Custom Field save API's own
     * HTML cleaning — so a real site is left with this RAW, uncleaned <details>/<summary>
     * value, which format_text() then strips down to broken, tag-less text on every render.
     * A field whose description exactly matches that raw signature is rebuilt with the
     * current <div>/<span>-based markup.
     *
     * @return void
     */
    public function test_upgrade_repairs_the_raw_details_based_description(): void {
        global $DB;

        $field = $DB->get_record('customfield_field', ['shortname' => 'objectives'], '*', MUST_EXIST);
        $DB->set_field(
            'customfield_field',
            'description',
            $this->broken_details_description('objectives'),
            ['id' => $field->id]
        );

        set_config('version', 2026072511, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072511);

        $updated = $DB->get_record('customfield_field', ['id' => $field->id], '*', MUST_EXIST);
        $this->assertSame(\mod_syllabus\local\help_text_builder::build('objectives'), $updated->description);
    }

    /**
     * A site where an admin re-saved the broken field via managefields.php in between (which
     * DOES clean on save, per core_customfield's own save path) is left with the CLEANED
     * broken signature instead of the raw one — also repaired.
     *
     * @return void
     */
    public function test_upgrade_repairs_the_cleaned_details_based_description(): void {
        global $DB;

        $field = $DB->get_record('customfield_field', ['shortname' => 'objectives'], '*', MUST_EXIST);
        $cleaned = clean_text($this->broken_details_description('objectives'), FORMAT_HTML);
        $DB->set_field('customfield_field', 'description', $cleaned, ['id' => $field->id]);

        set_config('version', 2026072511, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072511);

        $updated = $DB->get_record('customfield_field', ['id' => $field->id], '*', MUST_EXIST);
        $this->assertSame(\mod_syllabus\local\help_text_builder::build('objectives'), $updated->description);
    }

    /**
     * The repair step above must not touch a real customisation just because it does not
     * contain the plugin's own CSS class names — only an exact match of the reconstructed
     * broken signature is replaced.
     *
     * @return void
     */
    public function test_upgrade_repair_step_does_not_overwrite_a_customised_description(): void {
        global $DB;

        $field = $DB->get_record('customfield_field', ['shortname' => 'objectives'], '*', MUST_EXIST);
        $DB->set_field('customfield_field', 'description', 'Custom institutional wording.', ['id' => $field->id]);

        set_config('version', 2026072511, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072511);

        $updated = $DB->get_record('customfield_field', ['id' => $field->id], '*', MUST_EXIST);
        $this->assertSame('Custom institutional wording.', $updated->description);
    }

    /**
     * A field still holding the old toggle label (baked in at seed time) has just that <span>
     * replaced, the rest of the description left exactly as it was.
     *
     * @return void
     */
    public function test_upgrade_shortens_the_old_toggle_label(): void {
        global $DB;

        $field = $DB->get_record('customfield_field', ['shortname' => 'objectives'], '*', MUST_EXIST);
        $old = str_replace(
            \html_writer::span(get_string('viewmodelguidance', 'mod_syllabus'), 'syllabus-help-toggle'),
            \html_writer::span('Ver orientações do modelo', 'syllabus-help-toggle'),
            \mod_syllabus\local\help_text_builder::build('objectives')
        );
        $DB->set_field('customfield_field', 'description', $old, ['id' => $field->id]);

        set_config('version', 2026072513, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072513);

        $updated = $DB->get_record('customfield_field', ['id' => $field->id], '*', MUST_EXIST);
        $this->assertSame(\mod_syllabus\local\help_text_builder::build('objectives'), $updated->description);
    }

    /**
     * A description an institution rewrote so the old toggle label no longer appears in it
     * verbatim (e.g. they translated the toggle text themselves) is left byte-for-byte
     * untouched — the replacement only ever fires on an exact match of the known old label.
     *
     * @return void
     */
    public function test_upgrade_toggle_label_step_does_not_touch_a_rewritten_toggle(): void {
        global $DB;

        $field = $DB->get_record('customfield_field', ['shortname' => 'objectives'], '*', MUST_EXIST);
        $custom = 'Custom institutional wording with its own toggle text.';
        $DB->set_field('customfield_field', 'description', $custom, ['id' => $field->id]);

        set_config('version', 2026072513, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072513);

        $updated = $DB->get_record('customfield_field', ['id' => $field->id], '*', MUST_EXIST);
        $this->assertSame($custom, $updated->description);
    }

    /**
     * A site that installed the plugin before this correction had Avaliação Final modelled as
     * a flag on a regular week activity (isfinalassessment) — the upgrade step migrates that
     * activity's structural fields into the new plan-level columns and removes both the
     * activity and the now-unused column. Simulates a pre-existing site by adding the old
     * column back by hand before running the step (a fresh install never has it).
     *
     * @return void
     */
    public function test_upgrade_migrates_flagged_activity_into_final_assessment_fields(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $activitytable = new \xmldb_table('syllabus_activities');
        $isfinalassessmentfield = new \xmldb_field(
            'isfinalassessment',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            0
        );
        $dbman->add_field($activitytable, $isfinalassessmentfield);

        $course = $this->getDataGenerator()->create_course();
        $syllabus = $this->getDataGenerator()->create_module('syllabus', ['course' => $course->id]);
        $now = time();
        $weekid = $DB->insert_record('syllabus_weeks', [
            'syllabusid' => $syllabus->id, 'title' => 'Week 1', 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $activityid = $DB->insert_record('syllabus_activities', [
            'weekid' => $weekid, 'title' => 'Final exam', 'type' => 'quiz',
            'startdate' => $now, 'enddate' => $now + WEEKSECS, 'points' => 100,
            'isfinalassessment' => 1, 'sortorder' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        set_config('version', 2026072514, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072514);

        $updatedsyllabus = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        $this->assertSame('Final exam', $updatedsyllabus->finalassessmenttitle);
        $this->assertSame('quiz', $updatedsyllabus->finalassessmenttype);
        $this->assertEquals($now, $updatedsyllabus->finalassessmentstartdate);
        $this->assertEquals(100, $updatedsyllabus->finalassessmentpoints);
        $this->assertFalse($DB->record_exists('syllabus_activities', ['id' => $activityid]));
        $this->assertFalse($dbman->field_exists($activitytable, $isfinalassessmentfield));
    }

    /**
     * A site that installed the plugin before the finalassessmentinstructions Custom Field
     * existed never got it — the upgrade step adds it to the existing 'plan' category, without
     * duplicating the category or any of its other fields.
     *
     * @return void
     */
    public function test_upgrade_seeds_missing_final_assessment_instructions_field(): void {
        global $DB;

        $existing = $DB->get_record_sql(
            "SELECT f.id, c.id AS categoryid
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'mod_syllabus' AND c.area = 'plan' AND f.shortname = 'finalassessmentinstructions'",
            [],
            MUST_EXIST
        );
        $fieldcountbefore = $DB->count_records('customfield_field', ['categoryid' => $existing->categoryid]);
        $DB->delete_records('customfield_field', ['id' => $existing->id]);

        set_config('version', 2026072514, 'mod_syllabus');
        xmldb_syllabus_upgrade(2026072514);

        $readded = $DB->get_record_sql(
            "SELECT f.id, c.id AS categoryid
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = 'mod_syllabus' AND c.area = 'plan' AND f.shortname = 'finalassessmentinstructions'",
            [],
            MUST_EXIST
        );
        $this->assertSame($existing->categoryid, $readded->categoryid);
        $this->assertEquals(
            $fieldcountbefore,
            $DB->count_records('customfield_field', ['categoryid' => $readded->categoryid])
        );
    }
}
