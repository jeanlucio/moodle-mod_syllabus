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
 * AMD module for the edit-mode navigation rail and totals bar in Tab 1 ("Full plan").
 * Purely a client-side reading/scrolling aid — never validates or blocks a save.
 *
 * The other AMD modules that autosave a field (autosave.js, plan_details.js,
 * weeks_manager.js) dispatch a `syllabus-autosave` CustomEvent on `document` around each web
 * service call (`{detail: {pending: true}}` before, `{detail: {pending: false}}` after,
 * success or failure) instead of importing this module directly — a plain event keeps those
 * modules decoupled from whether a totals bar even exists on the page (e.g. read-only tabs).
 * This module is the only listener, aggregating every in-flight save into one chip.
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

/** @type {number} Count of currently in-flight autosave calls, across every AMD module. */
let pendingSaves = 0;

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
        let state = 'empty';
        if (filled === required.length) {
            symbol = '✓';
            label = stateLabels.complete;
            state = 'complete';
        } else if (filled > 0) {
            symbol = '!';
            label = stateLabels.partial;
            state = 'partial';
        }

        const icon = link.querySelector('.syllabus-rail-icon');
        const text = link.querySelector('.syllabus-rail-icon-text');
        if (icon) {
            icon.textContent = symbol;
            icon.dataset.state = state;
        }
        if (text) {
            text.textContent = label;
        }
    });
};

/**
 * Tolerance (in hours) for the duration match check — week duration inputs are whole hours
 * by convention, but nothing stops someone typing a decimal, so a strict equality check would
 * be too brittle.
 *
 * @type {number}
 */
const DURATION_MATCH_TOLERANCE_HOURS = 0.05;

/**
 * Recomputes the totals bar: summed week duration against the plan's own total workload —
 * both in hours — and, per grading stage, summed activity points against the fixed 100-point
 * scale, matching or not. An activity's stage is its own week's stage (read once per week,
 * applied to every activity inside it) — there is no per-activity stage selection anywhere.
 *
 * @param {HTMLElement} container
 */
const updateTotals = (container) => {
    let durationSum = 0;
    container.querySelectorAll('.syllabus-week-duration').forEach((el) => {
        durationSum += parseFloat(el.value) || 0;
    });
    const totaldurationInput = container.querySelector('.syllabus-plan-totalduration');
    const targetDuration = totaldurationInput ? (parseFloat(totaldurationInput.value) || 0) : 0;

    const pointsByStage = {};
    container.querySelectorAll('.syllabus-week-row').forEach((week) => {
        const stageSelect = week.querySelector('.syllabus-week-stage');
        const stage = stageSelect ? stageSelect.value : '1';
        let weekPoints = 0;
        week.querySelectorAll('.syllabus-activity-points').forEach((el) => {
            weekPoints += parseFloat(el.value) || 0;
        });
        pointsByStage[stage] = (pointsByStage[stage] || 0) + weekPoints;
    });

    const durationBar = container.querySelector('.syllabus-totals-duration');
    const durationValue = container.querySelector('.syllabus-totals-duration-value');
    const durationTarget = container.querySelector('.syllabus-totals-duration-target');

    if (durationValue) {
        durationValue.textContent = Math.round(durationSum * 10) / 10;
    }
    if (durationTarget) {
        durationTarget.textContent = targetDuration;
    }
    applyTotalsState(durationBar, Math.abs(durationSum - targetDuration) < DURATION_MATCH_TOLERANCE_HOURS);

    container.querySelectorAll('.syllabus-totals-points[data-stage]').forEach((chip) => {
        const pointsSum = pointsByStage[chip.dataset.stage] || 0;
        const valueEl = chip.querySelector('.syllabus-totals-points-value');
        if (valueEl) {
            valueEl.textContent = pointsSum;
        }
        applyTotalsState(chip, pointsSum === 100);
    });

    const grademethodSelect = container.querySelector('.syllabus-plan-grademethod');
    const grademethodValue = container.querySelector('.syllabus-totals-grademethod-value');
    if (grademethodSelect && grademethodValue) {
        grademethodValue.textContent = grademethodSelect.options[grademethodSelect.selectedIndex]?.text ?? '';
    }
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
 * Reads a `.syllabus-date-select` container's day/month selects as a "DD/MM" string, or an
 * empty string if either is unset — a compact numeric format (no month names needed) good
 * enough for a one-line week summary; the full date pickers elsewhere already show the
 * complete, localised value.
 *
 * @param {?HTMLElement} dateSelect
 * @returns {string}
 */
const formatShortDate = (dateSelect) => {
    if (!dateSelect) {
        return '';
    }
    const day = dateSelect.querySelector('.syllabus-date-day')?.value;
    const month = dateSelect.querySelector('.syllabus-date-month')?.value;
    if (!day || month === '' || month === undefined) {
        return '';
    }
    const paddedDay = day.padStart(2, '0');
    const paddedMonth = String(parseInt(month, 10) + 1).padStart(2, '0');
    return `${paddedDay}/${paddedMonth}`;
};

/**
 * Updates every week's collapsible summary line (title + "Xh · date range · N activities ·
 * Y pts") to reflect its current field values — called on the same input/change events that
 * already recompute the totals bar, so the summary never goes stale while editing.
 *
 * @param {HTMLElement} container
 */
const updateWeekSummaries = (container) => {
    container.querySelectorAll('.syllabus-week-row').forEach((week) => {
        const titleInput = week.querySelector('.syllabus-week-title');
        const titleEl = week.querySelector('.syllabus-week-summary-title');
        if (titleInput && titleEl) {
            titleEl.textContent = titleInput.value.trim();
        }

        const parts = [];
        const durationInput = week.querySelector('.syllabus-week-duration');
        if (durationInput && durationInput.value !== '') {
            parts.push(`${durationInput.value}h`);
        }
        const start = formatShortDate(week.querySelector('.syllabus-week-startdate'));
        const end = formatShortDate(week.querySelector('.syllabus-week-enddate'));
        if (start && end) {
            parts.push(`${start}–${end}`);
        } else if (start) {
            parts.push(start);
        }

        const activityRows = week.querySelectorAll('.syllabus-activity-row');
        if (activityRows.length) {
            parts.push(`${activityRows.length} ${activitiesLabel}`);
        }
        let pointsSum = 0;
        activityRows.forEach((row) => {
            pointsSum += parseFloat(row.querySelector('.syllabus-activity-points')?.value) || 0;
        });
        if (pointsSum > 0) {
            parts.push(`${pointsSum} ${pointsLabel}`);
        }

        const metaEl = week.querySelector('.syllabus-week-summary-meta');
        if (metaEl) {
            metaEl.textContent = parts.join(' · ');
        }
    });
};

/** @type {string} Cached "Activities"/"Points" labels for updateWeekSummaries(), filled by init(). */
let activitiesLabel = '';

/** @type {string} See activitiesLabel. */
let pointsLabel = '';

/**
 * Updates the "N weeks · M activities" chip from the current DOM state — weeks/activities are
 * only ever added or removed via a full page reload (see weeks_manager.js), so a plain count
 * at recompute time is always accurate without any extra event wiring.
 *
 * @param {HTMLElement} container
 */
const updateCounts = (container) => {
    const weeksValue = container.querySelector('.syllabus-totals-weeks-value');
    const activitiesValue = container.querySelector('.syllabus-totals-activities-value');
    if (weeksValue) {
        weeksValue.textContent = container.querySelectorAll('.syllabus-week-row').length;
    }
    if (activitiesValue) {
        activitiesValue.textContent = container.querySelectorAll('.syllabus-activity-row').length;
    }
};

/**
 * Sets the "Collapse all"/"Expand all" button's label to match the weeks' current open/closed
 * state — called after the button's own click, and whenever a week is opened/closed
 * individually (its native `<summary>` disclosure triangle, entirely outside this button's
 * control), so the label never promises the opposite of what the next click would actually do.
 *
 * @param {HTMLElement} container
 * @param {HTMLElement} button
 */
const updateCollapseAllLabel = (container, button) => {
    const weeks = container.querySelectorAll('.syllabus-week-row');
    const anyOpen = [...weeks].some((week) => week.open);
    button.textContent = anyOpen ? button.dataset.collapse : button.dataset.expand;
};

/**
 * Toggles every week's [open] attribute at once — collapses if any week is currently open,
 * expands otherwise. Also keeps the button's own label in sync with weeks opened/closed
 * individually via their native `<summary>`, which this button never otherwise hears about.
 *
 * @param {HTMLElement} container
 */
const wireCollapseAll = (container) => {
    const button = container.querySelector('.syllabus-collapse-all');
    if (!button) {
        return;
    }
    button.addEventListener('click', () => {
        const weeks = container.querySelectorAll('.syllabus-week-row');
        const shouldOpen = ![...weeks].some((week) => week.open);
        weeks.forEach((week) => {
            week.open = shouldOpen;
        });
        updateCollapseAllLabel(container, button);
    });
    container.querySelectorAll('.syllabus-week-row').forEach((week) => {
        week.addEventListener('toggle', () => updateCollapseAllLabel(container, button));
    });
    updateCollapseAllLabel(container, button);
};

/**
 * Listens for the `syllabus-autosave` event other AMD modules dispatch on `document` around
 * each web service call, aggregating them into the totals bar's single "Saving.../All changes
 * saved" chip.
 *
 * @param {HTMLElement} container
 */
const wireAutosaveChip = (container) => {
    const chip = container.querySelector('.syllabus-totals-autosave');
    if (!chip) {
        return;
    }
    document.addEventListener('syllabus-autosave', (e) => {
        pendingSaves = Math.max(0, pendingSaves + (e.detail.pending ? 1 : -1));
        const isPending = pendingSaves > 0;
        chip.classList.toggle('syllabus-totals-pending', isPending);
        chip.textContent = isPending ? chip.dataset.pending : chip.dataset.idle;
    });
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
 * Highlights whichever rail link points at the section currently nearest the top of the
 * viewport — a purely visual "you are here" cue, same non-blocking spirit as the ✓/!/○ icons.
 * Falls back to doing nothing where IntersectionObserver isn't available (old browser); the
 * rail still works for reading state and clicking to scroll either way.
 *
 * @param {HTMLElement} container
 */
const wireScrollSpy = (container) => {
    if (typeof IntersectionObserver === 'undefined') {
        return;
    }
    const sections = container.querySelectorAll('[data-syllabus-section]');
    if (!sections.length) {
        return;
    }
    const setActive = (id) => {
        container.querySelectorAll('.syllabus-rail-link').forEach((link) => {
            link.classList.toggle('syllabus-rail-link-active', link.getAttribute('href') === `#${id}`);
        });
    };
    const observer = new IntersectionObserver((entries) => {
        const visible = entries.filter((entry) => entry.isIntersecting);
        if (!visible.length) {
            return;
        }
        visible.sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        setActive(visible[0].target.id);
    }, {rootMargin: '-10% 0px -70% 0px'});
    sections.forEach((section) => {
        if (section.id) {
            observer.observe(section);
        }
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

    const [complete, empty, partial, match, mismatch, activities, points] = await getStrings([
        {key: 'sectionstatecomplete', component: 'mod_syllabus'},
        {key: 'sectionstateempty', component: 'mod_syllabus'},
        {key: 'sectionstatepartial', component: 'mod_syllabus'},
        {key: 'totalsmatch', component: 'mod_syllabus'},
        {key: 'totalsmismatch', component: 'mod_syllabus'},
        {key: 'activities', component: 'mod_syllabus'},
        {key: 'activitypoints', component: 'mod_syllabus'},
    ]);
    stateLabels = {complete, empty, partial};
    totalsLabels = {match, mismatch};
    activitiesLabel = activities;
    pointsLabel = points;

    wireRailLinks(container);
    wireScrollSpy(container);
    wireCollapseAll(container);
    wireAutosaveChip(container);

    const recompute = () => {
        updateSectionStates(container);
        updateTotals(container);
        updateCounts(container);
        updateWeekSummaries(container);
    };
    recompute();
    container.addEventListener('input', recompute);
    container.addEventListener('change', recompute);
};
