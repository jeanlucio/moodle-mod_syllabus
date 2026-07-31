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
 * Upgrade steps for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs mod_syllabus's upgrade steps.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_syllabus_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026072510) {
        // A site that installed this plugin before the "<shortname>help" lang strings existed
        // never had its narrative Custom Fields' short description backfilled — xmldb_syllabus_
        // install() only runs once, at initial install, never again on upgrade. Fill in the
        // description (and its format) for any field still missing one, scoped to this
        // plugin's own three areas so a same-named field belonging to another component is
        // never touched. A field an institution already customised via managefields.php (a
        // non-empty description) is left exactly as it is.
        $shortnames = [
            'coursedescription', 'objectives', 'contents', 'methodology',
            'presentationscript', 'generalreferences',
            'details', 'supportmaterial', 'supplementarymaterial', 'interactiontools', 'notes',
            'studentinstructions', 'gradingcriteria', 'tutorguidance',
        ];
        [$shortnamesql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $params['component'] = 'mod_syllabus';
        $fields = $DB->get_records_sql(
            "SELECT f.id, f.shortname
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component
                AND f.shortname $shortnamesql
                AND (f.description IS NULL OR " . $DB->sql_compare_text('f.description') . " = '')",
            $params
        );
        foreach ($fields as $field) {
            $DB->update_record('customfield_field', (object) [
                'id' => $field->id,
                'description' => get_string($field->shortname . 'help', 'mod_syllabus'),
                'descriptionformat' => FORMAT_HTML,
            ]);
        }

        upgrade_mod_savepoint(true, 2026072510, 'syllabus');
    }

    if ($oldversion < 2026072511) {
        // The 14 narrative fields' description held only the short summary (backfilled by the
        // step above, or seeded that way by any pre-5.6.c install) — it now holds that summary
        // plus the full model guidance behind a disclosure (help_text_builder::build()).
        // Replace the description only where it is still exactly that short-only text (or
        // empty); an institution that has already rewritten it via managefields.php is left
        // untouched.
        $narrativeshortnames = [
            'coursedescription', 'objectives', 'contents', 'methodology',
            'presentationscript', 'generalreferences',
            'details', 'supportmaterial', 'supplementarymaterial', 'interactiontools', 'notes',
            'studentinstructions', 'gradingcriteria', 'tutorguidance',
        ];
        [$shortnamesql, $params] = $DB->get_in_or_equal($narrativeshortnames, SQL_PARAMS_NAMED);
        $params['component'] = 'mod_syllabus';
        $fields = $DB->get_records_sql(
            "SELECT f.id, f.shortname, f.description
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component
                AND f.shortname $shortnamesql",
            $params
        );
        foreach ($fields as $field) {
            $current = (string) $field->description;
            $shortonly = get_string($field->shortname . 'help', 'mod_syllabus');
            if ($current === '' || $current === $shortonly) {
                $DB->update_record('customfield_field', (object) [
                    'id' => $field->id,
                    'description' => \mod_syllabus\local\help_text_builder::build($field->shortname),
                    'descriptionformat' => FORMAT_HTML,
                ]);
            }
        }

        // A site that installed the plugin before the 'help' Custom Field area existed never
        // got it — seed it now, exactly as a fresh install would (same shared definitions and
        // seeding logic as db/install.php, via customfield_seeder).
        if (!$DB->record_exists('customfield_category', ['component' => 'mod_syllabus', 'area' => 'help'])) {
            $areas = \mod_syllabus\local\customfield_seeder::areas();
            \mod_syllabus\local\customfield_seeder::seed_area('help_handler', $areas['help_handler']);
        }

        upgrade_mod_savepoint(true, 2026072511, 'syllabus');
    }

    if ($oldversion < 2026072513) {
        // The step above built the combined description with <details>/<summary>. This upgrade
        // step writes straight to customfield_field via update_record(), bypassing the Custom
        // Field save API's own HTML cleaning entirely — so the value it wrote is the RAW,
        // uncleaned <details>/<summary> markup, still sitting in the database exactly as
        // built. The bug only shows up at *display* time: format_text() (called by both
        // plan_reader::export_editable_fields() and the Custom Field admin UI) cleans the
        // description on every read, and confirmed live that its cleaner strips
        // <details>/<summary> entirely — along with role, tabindex, aria- and data-
        // attributes — leaving the professor's edit form showing broken, tag-less text.
        // help_text_builder::build() now uses plain <div>/<span> with only a `class`
        // attribute instead (confirmed live to survive that same cleaning). Rebuild only a
        // field whose description exactly matches the broken signature — either the raw
        // version actually written above, or (for extra safety, e.g. a site where an admin
        // re-saved it via managefields.php in between, which DOES clean on save) the cleaned
        // version — or is empty. Never merely "doesn't look like the new format", which would
        // risk overwriting a real institutional customisation that just doesn't happen to
        // mention this plugin's own CSS class names.
        $allshortnames = [
            'coursedescription', 'objectives', 'contents', 'methodology',
            'presentationscript', 'generalreferences',
            'details', 'supportmaterial', 'supplementarymaterial', 'interactiontools', 'notes',
            'studentinstructions', 'gradingcriteria', 'tutorguidance',
            'characterisation', 'weekplanning', 'syncmeeting', 'activitytype', 'finalassessment',
        ];
        [$shortnamesql, $params] = $DB->get_in_or_equal($allshortnames, SQL_PARAMS_NAMED);
        $params['component'] = 'mod_syllabus';
        $fields = $DB->get_records_sql(
            "SELECT f.id, f.shortname, f.description
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component
                AND f.shortname $shortnamesql",
            $params
        );
        foreach ($fields as $field) {
            $current = (string) $field->description;
            $brokenraw = \html_writer::tag('p', get_string($field->shortname . 'help', 'mod_syllabus')) .
                \html_writer::tag(
                    'details',
                    \html_writer::tag('summary', get_string('viewmodelguidance', 'mod_syllabus')) .
                        \html_writer::tag('p', get_string($field->shortname . 'helpfull', 'mod_syllabus')),
                    ['class' => 'syllabus-field-help-full']
                );
            $brokencleaned = clean_text($brokenraw, FORMAT_HTML);
            if ($current === '' || $current === $brokenraw || $current === $brokencleaned) {
                $DB->update_record('customfield_field', (object) [
                    'id' => $field->id,
                    'description' => \mod_syllabus\local\help_text_builder::build($field->shortname),
                    'descriptionformat' => FORMAT_HTML,
                ]);
            }
        }

        upgrade_mod_savepoint(true, 2026072513, 'syllabus');
    }

    if ($oldversion < 2026072514) {
        // The toggle label is baked once into each field's description at seed time
        // (help_text_builder::build()), so an already-seeded site keeps whatever label was
        // baked in until this step runs. Replace only the toggle <span>'s old text, not the
        // whole description, so it still applies even if an institution has already
        // customised some other part of the same description via managefields.php; a
        // description that no longer contains either shipped language's old label exactly
        // (because the institution rewrote the toggle text itself) is left untouched.
        $oldlabels = [
            \html_writer::span('View model guidance', 'syllabus-help-toggle'),
            \html_writer::span('Ver orientações do modelo', 'syllabus-help-toggle'),
        ];
        $newlabel = \html_writer::span(get_string('viewmodelguidance', 'mod_syllabus'), 'syllabus-help-toggle');
        $allshortnames = [
            'coursedescription', 'objectives', 'contents', 'methodology',
            'presentationscript', 'generalreferences',
            'details', 'supportmaterial', 'supplementarymaterial', 'interactiontools', 'notes',
            'studentinstructions', 'gradingcriteria', 'tutorguidance',
            'characterisation', 'weekplanning', 'syncmeeting', 'activitytype', 'finalassessment',
        ];
        [$shortnamesql, $params] = $DB->get_in_or_equal($allshortnames, SQL_PARAMS_NAMED);
        $params['component'] = 'mod_syllabus';
        $fields = $DB->get_records_sql(
            "SELECT f.id, f.description
               FROM {customfield_field} f
               JOIN {customfield_category} c ON c.id = f.categoryid
              WHERE c.component = :component
                AND f.shortname $shortnamesql",
            $params
        );
        foreach ($fields as $field) {
            $current = (string) $field->description;
            $updated = str_replace($oldlabels, $newlabel, $current);
            if ($updated !== $current) {
                $DB->update_record('customfield_field', (object) [
                    'id' => $field->id,
                    'description' => $updated,
                ]);
            }
        }

        upgrade_mod_savepoint(true, 2026072514, 'syllabus');
    }

    if ($oldversion < 2026072515) {
        $dbman = $DB->get_manager();

        $syllabustable = new xmldb_table('syllabus');
        $newsyllabusfields = [
            new xmldb_field('stagecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 1),
            new xmldb_field('grademethod', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'sum'),
            new xmldb_field('finalassessmenttitle', XMLDB_TYPE_CHAR, '255'),
            new xmldb_field('finalassessmenttype', XMLDB_TYPE_CHAR, '100'),
            new xmldb_field('finalassessmentstartdate', XMLDB_TYPE_INTEGER, '10'),
            new xmldb_field('finalassessmentenddate', XMLDB_TYPE_INTEGER, '10'),
            new xmldb_field('finalassessmentpoints', XMLDB_TYPE_NUMBER, '10, 2'),
        ];
        foreach ($newsyllabusfields as $field) {
            if (!$dbman->field_exists($syllabustable, $field)) {
                $dbman->add_field($syllabustable, $field);
            }
        }

        $weektable = new xmldb_table('syllabus_weeks');
        $stagefield = new xmldb_field('stage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 1);
        if (!$dbman->field_exists($weektable, $stagefield)) {
            $dbman->add_field($weektable, $stagefield);
        }

        // Avaliação Final was originally modelled as a flag on a regular week activity
        // (isfinalassessment) — the source document instead treats it as its own standalone
        // block, independent of any week. Migrate any such flagged activity's structural
        // fields into the new plan-level columns before dropping the column; when more than
        // one activity was flagged for the same plan, the last one processed wins (this
        // plugin has never been released, so this is dev-only data). The activity's own
        // narrative "Student instructions" Custom Field value is not carried over
        // automatically — re-enter it manually in the new Final assessment section if needed.
        $activitytable = new xmldb_table('syllabus_activities');
        $isfinalassessmentfield = new xmldb_field('isfinalassessment');
        if ($dbman->field_exists($activitytable, $isfinalassessmentfield)) {
            $flagged = $DB->get_records_sql(
                "SELECT a.id, a.title, a.type, a.startdate, a.enddate, a.points, w.syllabusid
                   FROM {syllabus_activities} a
                   JOIN {syllabus_weeks} w ON w.id = a.weekid
                  WHERE a.isfinalassessment = 1"
            );
            foreach ($flagged as $activity) {
                $DB->update_record('syllabus', (object) [
                    'id'                       => $activity->syllabusid,
                    'finalassessmenttitle'     => $activity->title,
                    'finalassessmenttype'      => $activity->type,
                    'finalassessmentstartdate' => $activity->startdate,
                    'finalassessmentenddate'   => $activity->enddate,
                    'finalassessmentpoints'    => $activity->points,
                ]);
                $DB->delete_records('syllabus_activities', ['id' => $activity->id]);
            }
            $dbman->drop_field($activitytable, $isfinalassessmentfield);
        }

        // A site that installed the plugin before this field existed never got it — add it to
        // the existing 'plan' Custom Field category, exactly as a fresh install would (same
        // seeding logic as db/install.php, via customfield_seeder).
        \mod_syllabus\local\customfield_seeder::ensure_field(
            'plan_handler',
            'finalassessmentinstructions',
            'studentinstructions'
        );

        upgrade_mod_savepoint(true, 2026072515, 'syllabus');
    }

    if ($oldversion < 2026073100) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('syllabus_review_notes');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('syllabusid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('area', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('note', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('reviewerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_syllabus', XMLDB_KEY_FOREIGN, ['syllabusid'], 'syllabus', ['id']);
        $table->add_key('fk_reviewer', XMLDB_KEY_FOREIGN, ['reviewerid'], 'user', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026073100, 'syllabus');
    }

    return true;
}
