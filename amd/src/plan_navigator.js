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
 * AMD module for the edit-mode navigation rail and totals bar in Tab 1 ("Plano completo").
 * Purely a client-side reading/scrolling aid — never validates or blocks a save, matching the
 * "heurística simples, não validação bloqueante" rule in SCOPE §8.
 *
 * @module     mod_syllabus/plan_navigator
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';

/** @type {Object<string, string>} Section-state label cache, filled once by init(). */
let stateLabels = {};

/** @type {Object<string, string>} Totals-match label cache, filled once by init(). */
let totalsLabels = {};

/**
 * Updates every rail link's icon/label to reflect whether the section it points to has all,
 * some, or none of its `.syllabus-required-input` elements filled in.
 *
 * @param {HTMLElement} container
 */
const updateSectionStates = (container) => {
    container.querySelectorAll('[data-syllabus-section]').forEach((section) => {
        if (!section.id) {
            return;
        }
        const required = section.querySelectorAll('.syllabus-required-input');
        if (!required.length) {
            return;
        }
        const link = container.querySelector(`.syllabus-rail-link[href="#${section.id}"]`);
        if (!link) {
            return;
        }
        let filled = 0;
        required.forEach((el) => {
            if (el.value && el.value.trim() !== '') {
                filled++;
            }
        });

        let symbol = '○';
        let label = stateLabels.empty;
        if (filled === required.length) {
            symbol = '✓';
            label = stateLabels.complete;
        } else if (filled > 0) {
            symbol = '!';
            label = stateLabels.partial;
        }

        const icon = link.querySelector('.syllabus-rail-icon');
        const text = link.querySelector('.syllabus-rail-icon-text');
        if (icon) {
            icon.textContent = symbol;
        }
        if (text) {
            text.textContent = label;
        }
    });
};

/**
 * Recomputes the totals bar: summed week duration against the plan's own total workload, and
 * summed activity points against the fixed 100-point scale, matching or not.
 *
 * @param {HTMLElement} container
 */
const updateTotals = (container) => {
    let durationSum = 0;
    container.querySelectorAll('.syllabus-week-duration').forEach((el) => {
        durationSum += parseInt(el.value, 10) || 0;
    });
    const totaldurationInput = container.querySelector('.syllabus-plan-totalduration');
    const targetDuration = totaldurationInput ? (parseInt(totaldurationInput.value, 10) || 0) : 0;

    let pointsSum = 0;
    container.querySelectorAll('.syllabus-activity-points').forEach((el) => {
        pointsSum += parseFloat(el.value) || 0;
    });

    const durationBar = container.querySelector('.syllabus-totals-duration');
    const pointsBar = container.querySelector('.syllabus-totals-points');
    const durationValue = container.querySelector('.syllabus-totals-duration-value');
    const durationTarget = container.querySelector('.syllabus-totals-duration-target');
    const pointsValue = container.querySelector('.syllabus-totals-points-value');

    if (durationValue) {
        durationValue.textContent = durationSum;
    }
    if (durationTarget) {
        durationTarget.textContent = targetDuration;
    }
    if (pointsValue) {
        pointsValue.textContent = pointsSum;
    }

    applyTotalsState(durationBar, durationSum === targetDuration);
    applyTotalsState(pointsBar, pointsSum === 100);
};

/**
 * Toggles a totals bar segment's match/mismatch class and its visually-hidden text — the
 * colour alone is never the only signal, per the plugin's accessibility conventions.
 *
 * @param {?HTMLElement} el
 * @param {boolean} matches
 */
const applyTotalsState = (el, matches) => {
    if (!el) {
        return;
    }
    el.classList.toggle('syllabus-totals-ok', matches);
    el.classList.toggle('syllabus-totals-mismatch', !matches);
    let text = el.querySelector('.syllabus-totals-state-text');
    if (!text) {
        text = document.createElement('span');
        text.className = 'visually-hidden syllabus-totals-state-text';
        el.appendChild(text);
    }
    text.textContent = matches ? totalsLabels.match : totalsLabels.mismatch;
};

/**
 * Intercepts rail link clicks to scroll smoothly to their target section, respecting
 * prefers-reduced-motion; falls back to the browser's native anchor jump if the target isn't
 * found (should not happen for a server-rendered rail against server-rendered sections).
 *
 * @param {HTMLElement} container
 */
const wireRailLinks = (container) => {
    container.querySelectorAll('.syllabus-rail-link').forEach((link) => {
        link.addEventListener('click', (e) => {
            const targetId = link.getAttribute('href').slice(1);
            const target = document.getElementById(targetId);
            if (!target) {
                return;
            }
            e.preventDefault();
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            target.scrollIntoView({behavior: reduceMotion ? 'auto' : 'smooth', block: 'start'});
        });
    });
};

/**
 * Initialises the navigation rail and totals bar for the edit-mode form.
 */
export const init = async() => {
    const container = document.querySelector('.syllabus-edit-content');
    if (!container) {
        return;
    }

    const [complete, empty, partial, match, mismatch] = await getStrings([
        {key: 'sectionstatecomplete', component: 'mod_syllabus'},
        {key: 'sectionstateempty', component: 'mod_syllabus'},
        {key: 'sectionstatepartial', component: 'mod_syllabus'},
        {key: 'totalsmatch', component: 'mod_syllabus'},
        {key: 'totalsmismatch', component: 'mod_syllabus'},
    ]);
    stateLabels = {complete, empty, partial};
    totalsLabels = {match, mismatch};

    wireRailLinks(container);

    const recompute = () => {
        updateSectionStates(container);
        updateTotals(container);
    };
    recompute();
    container.addEventListener('input', recompute);
    container.addEventListener('change', recompute);
};
