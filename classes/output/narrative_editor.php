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

namespace mod_syllabus\output;

use context;
use editor_tiny\editor as tiny_editor;
use editor_tiny\manager as tiny_manager;
use stdClass;

/**
 * Builds the shared Tiny configuration used by every narrative field on the edit-mode form
 * (Fase 5.5.e), without ever calling \editor_tiny\editor::use_editor() itself — that method
 * always queues its setup call onto $PAGE->requires for immediate execution at page load,
 * which is exactly the eager-instantiation behaviour lazy init needs to avoid.
 *
 * The two private pieces this mirrors — \editor_tiny\editor::use_editor()'s config array and
 * \core_form\filetypes_util-adjacent $fpoptions construction from
 * MoodleQuickForm_editor::toHtml() — are the "~70 lines the core keeps private" noted in
 * SCOPE §17's Fase 3 entry. This class only replicates the first (the config array itself,
 * built from public APIs: editor_tiny\manager::get_plugin_configuration() is public). The
 * second ($fpoptions per media type via initialise_filepicker()) is deliberately skipped —
 * the shared config below passes an empty filepicker option set, so image/media insert
 * plugins that check for it self-disable rather than offer a broken picker (the same
 * behaviour core's own plugin::is_enabled() already documents for "no file storage"). Wiring
 * a working filepicker per field is a natural follow-up, not silently promised here.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class narrative_editor {
    /**
     * Whether the current user's preferred editor is Tiny — respects the same
     * editors_get_preferred_editor() the core mform editor element uses, so a user who has
     * chosen the plain textarea editor in their profile keeps getting exactly that, with no
     * lazy-load apparatus attached at all.
     *
     * @return bool
     */
    public static function is_tiny_available(): bool {
        $editor = editors_get_preferred_editor(FORMAT_HTML);
        return $editor instanceof tiny_editor;
    }

    /**
     * Builds the Tiny configuration object shared by every narrative field on the page.
     * Every field merges its own draftitemid into a copy of this object client-side before
     * attaching — everything else (plugin list, language, branding) is identical per page,
     * so there is no reason to rebuild it per field.
     *
     * @param context $context The activity's module context.
     * @return stdClass
     */
    public static function base_config(context $context): stdClass {
        global $PAGE;

        $manager = new tiny_manager();
        tiny_editor::set_default_configuration($manager);

        $siteconfig = get_config('editor_tiny');
        $options = ['context' => $context];
        $fpoptions = [];

        return (object) [
            'css' => $PAGE->theme->editor_css_url()->out(false),
            'context' => $context->id,
            'filepicker' => (object) $fpoptions,
            'draftitemid' => 0,
            'currentLanguage' => current_language(),
            'branding' => property_exists($siteconfig, 'branding') ? !empty($siteconfig->branding) : true,
            'extended_valid_elements' => $siteconfig->extended_valid_elements ?? 'script[*],p[*],i[*]',
            'language' => [
                'currentlang' => current_language(),
                'installed' => get_string_manager()->get_list_of_translations(true),
                'available' => get_string_manager()->get_list_of_languages(),
            ],
            'placeholderSelectors' => [],
            'plugins' => $manager->get_plugin_configuration($context, $options, $fpoptions, new tiny_editor()),
        ];
    }
}
