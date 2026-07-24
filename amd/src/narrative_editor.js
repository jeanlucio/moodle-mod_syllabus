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
 * AMD module for the lazily-initialised Tiny editor on narrative Custom Fields.
 *
 * Each field starts as a focusable, formatted read-only preview styled to look like an open
 * text field. A plain 'focus' listener (covers both click and Tab) swaps in the underlying
 * `<textarea>` and attaches a real Tiny instance via editor_tiny/editor's own
 * setupForTarget() — the same API a normal mform editor element ends up calling, just invoked
 * on demand instead of at page load. No separate activation control is needed: the preview's
 * own visible content already tells a keyboard/screen-reader user what it is, satisfying
 * WCAG's "on focus" concern without an extra button. Losing focus removes the instance again,
 * so a plan with many narrative fields never has more than a couple of live editors at once.
 *
 * Deliberately does not wire a working filepicker (image/media/link upload) — see
 * classes/output/narrative_editor.php's docblock for why the shared config passes empty
 * filepicker options and what that does to Tiny's media-insert plugins.
 *
 * @module     mod_syllabus/narrative_editor
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setupForTarget, getInstanceForElementId} from 'editor_tiny/editor';

/** @type {?Object} The shared Tiny configuration, parsed once by init(). */
let baseConfig = null;

/**
 * Lazily attaches a Tiny instance to a narrative field's textarea, replacing its read-only
 * preview. A no-op if an instance is already live for this field (double-activation guard).
 *
 * @param {HTMLElement} wrapper The .syllabus-narrative-field element.
 */
const activateField = async(wrapper) => {
    const preview = wrapper.querySelector('.syllabus-narrative-preview');
    const textarea = wrapper.querySelector('.syllabus-customfield-editor');
    if (!preview || !textarea || getInstanceForElementId(textarea.id)) {
        return;
    }

    preview.hidden = true;
    textarea.classList.remove('d-none');

    const config = Object.assign({}, baseConfig, {
        draftitemid: parseInt(textarea.dataset.itemid, 10) || 0,
    });
    const editor = await setupForTarget(textarea, config);

    // Tiny only syncs its content back into the textarea on save()/blur/submit, not on every
    // keystroke — so autosave.js's own 'input' listener on the textarea would otherwise never
    // fire while typing inside Tiny. Forcing a save + a real dispatched 'input' event here
    // reuses that existing debounced-autosave path unchanged, rather than duplicating it.
    editor.on('input change keyup', () => {
        editor.save();
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
    });
    editor.on('blur', () => deactivateField(wrapper));
    editor.focus();
};

/**
 * Removes a field's live Tiny instance and restores its read-only preview.
 *
 * @param {HTMLElement} wrapper The .syllabus-narrative-field element.
 */
const deactivateField = (wrapper) => {
    const preview = wrapper.querySelector('.syllabus-narrative-preview');
    const textarea = wrapper.querySelector('.syllabus-customfield-editor');
    const instance = textarea ? getInstanceForElementId(textarea.id) : null;
    if (!instance) {
        return;
    }

    instance.save();
    if (preview) {
        preview.innerHTML = textarea.value;
        preview.hidden = false;
        // Returning focus to the preview is itself a 'focus' event — without this guard the
        // listener below would immediately reactivate the field it just closed.
        preview.dataset.restoringFocus = '1';
        preview.focus();
    }
    instance.remove();
    textarea.classList.add('d-none');
};

/**
 * Reads the shared Tiny configuration and wires every narrative field on the page for lazy
 * editing. A no-op wherever the author's preferred editor isn't Tiny — the config script this
 * looks for is only rendered when narrative_editor::is_tiny_available() was true server-side.
 */
export const init = () => {
    const configEl = document.getElementById('syllabus-tiny-config');
    if (!configEl) {
        return;
    }
    baseConfig = JSON.parse(configEl.textContent);

    document.querySelectorAll('.syllabus-narrative-field').forEach((wrapper) => {
        const preview = wrapper.querySelector('.syllabus-narrative-preview');
        if (preview) {
            preview.addEventListener('focus', () => {
                if (preview.dataset.restoringFocus) {
                    delete preview.dataset.restoringFocus;
                    return;
                }
                activateField(wrapper);
            });
        }
    });
};
