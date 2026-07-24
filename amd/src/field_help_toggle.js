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
 * AMD module for the "View model guidance" disclosure on every field's help text.
 *
 * The disclosure markup (.syllabus-field-help-full > .syllabus-help-toggle +
 * .syllabus-help-body) is stored HTML — either a narrative Custom Field's description or one
 * of the 'help' area's structural field descriptions, both institution-editable via
 * managefields.php. Moodle's HTML cleaner (the same one both the Custom Field save path and
 * format_text() apply) strips role, tabindex, aria- and data- attributes, and would strip
 * <details>/<summary> entirely — so unlike the collapsible week fieldset (a native <details>
 * this plugin itself renders, never round-tripped through admin-edited HTML), this disclosure
 * gets its interactivity and accessibility attributes here instead, once per page load,
 * because none of that (role, tabindex, aria- and data- attributes) can survive being stored.
 *
 * @module     mod_syllabus/field_help_toggle
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Toggles one disclosure's open state, keeping its toggle's aria-expanded in sync.
 *
 * @param {HTMLElement} toggle The .syllabus-help-toggle element.
 */
const toggleDisclosure = (toggle) => {
    const container = toggle.closest('.syllabus-field-help-full');
    if (!container) {
        return;
    }
    const open = !container.classList.contains('syllabus-help-open');
    container.classList.toggle('syllabus-help-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
};

/**
 * Wires every "View model guidance" toggle found within the page's syllabus content.
 */
export const init = () => {
    const container = document.querySelector('.path-mod-syllabus');
    if (!container) {
        return;
    }

    container.querySelectorAll('.syllabus-help-toggle').forEach((toggle) => {
        toggle.setAttribute('role', 'button');
        toggle.setAttribute('tabindex', '0');
        toggle.setAttribute('aria-expanded', 'false');

        toggle.addEventListener('click', () => toggleDisclosure(toggle));
        toggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleDisclosure(toggle);
            }
        });
    });
};
