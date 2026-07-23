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
 * AMD module for the real day/month/year date pickers used outside a mform (activity
 * start/end dates, plan course start/end dates) — SCOPE §8 "date-pickers reais em vez de
 * <input type=\"date\"> cru", mirroring the day/month/year selects of core's own
 * element-date_selector.mustache. Each `.syllabus-date-select` container is rendered as 3
 * empty `<select>` shells (see templates/date_select.mustache); this module fills their
 * options and initial selection. Localized month names come from the server via init()'s
 * argument — userdate()-backed (core_calendar\type_factory::get_months()), no simple
 * translatable string exists for them, so they cannot be fetched through core/str.
 *
 * @module     mod_syllabus/plan_dateselect
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

/** @type {string[]} Localized month names, index 0 = January, cached from init(). */
let monthNames = [];

/** @type {string} "Choose..." placeholder label, cached from init(). */
let chooseLabel = '';

/** @type {int} How many years before the current year the year select offers. */
const YEARS_BEFORE = 2;

/** @type {int} How many years after the current year the year select offers. */
const YEARS_AFTER = 5;

/**
 * Builds one <option>.
 *
 * @param {string} value
 * @param {string} label
 * @returns {HTMLOptionElement}
 */
const buildOption = (value, label) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    return option;
};

/**
 * Fills a container's 3 empty <select> shells with day/month/year options, each starting
 * with a blank "Choose..." option representing "no date set". Safe to call more than once on
 * the same container — one whose day select already has options is left untouched.
 *
 * @param {HTMLElement} container A .syllabus-date-select element.
 */
export const populate = (container) => {
    const daySelect = container.querySelector('.syllabus-date-day');
    const monthSelect = container.querySelector('.syllabus-date-month');
    const yearSelect = container.querySelector('.syllabus-date-year');
    if (!daySelect || !monthSelect || !yearSelect || daySelect.options.length) {
        return;
    }

    daySelect.appendChild(buildOption('', chooseLabel));
    for (let day = 1; day <= 31; day++) {
        daySelect.appendChild(buildOption(String(day), String(day)));
    }

    monthSelect.appendChild(buildOption('', chooseLabel));
    monthNames.forEach((name, index) => {
        monthSelect.appendChild(buildOption(String(index), name));
    });

    const currentYear = new Date().getFullYear();
    yearSelect.appendChild(buildOption('', chooseLabel));
    for (let year = currentYear - YEARS_BEFORE; year <= currentYear + YEARS_AFTER; year++) {
        yearSelect.appendChild(buildOption(String(year), String(year)));
    }
};

/**
 * Sets a populated container's 3 selects from its own data-timestamp attribute. A missing or
 * zero timestamp leaves the selects on their blank placeholder, matching "no date set".
 *
 * @param {HTMLElement} container
 */
export const hydrate = (container) => {
    const timestamp = parseInt(container.dataset.timestamp, 10);
    if (!timestamp) {
        return;
    }
    const date = new Date(timestamp * 1000);
    const daySelect = container.querySelector('.syllabus-date-day');
    const monthSelect = container.querySelector('.syllabus-date-month');
    const yearSelect = container.querySelector('.syllabus-date-year');
    if (daySelect) {
        daySelect.value = String(date.getUTCDate());
    }
    if (monthSelect) {
        monthSelect.value = String(date.getUTCMonth());
    }
    if (yearSelect) {
        yearSelect.value = String(date.getUTCFullYear());
    }
};

/**
 * Reads a container's 3 selects as a UTC-midnight Unix timestamp, or null if any of the three
 * is left on the blank placeholder — the same "optional date" contract the plain
 * `<input type="date">` this replaces already had.
 *
 * @param {?HTMLElement} container
 * @returns {?int}
 */
export const readTimestamp = (container) => {
    if (!container) {
        return null;
    }
    const day = container.querySelector('.syllabus-date-day').value;
    const month = container.querySelector('.syllabus-date-month').value;
    const year = container.querySelector('.syllabus-date-year').value;
    if (day === '' || month === '' || year === '') {
        return null;
    }
    return Math.floor(Date.UTC(parseInt(year, 10), parseInt(month, 10), parseInt(day, 10)) / 1000);
};

/**
 * Populates and hydrates every `.syllabus-date-select` found within a given container — used
 * both for the whole-page pass at init() and for a single freshly client-built row.
 *
 * @param {HTMLElement} container
 */
export const wireContainer = (container) => {
    container.querySelectorAll('.syllabus-date-select').forEach((el) => {
        populate(el);
        hydrate(el);
    });
};

/**
 * Fetches the localized "Choose..." placeholder, caches the server-provided month names, and
 * wires up every `.syllabus-date-select` already on the page.
 *
 * @param {string[]} monthnames 12 localized month names, index 0 = January.
 */
export const init = async(monthnames) => {
    monthNames = monthnames || [];
    chooseLabel = await getString('choosedots', 'core');

    const container = document.querySelector('.path-mod-syllabus');
    if (!container) {
        return;
    }
    wireContainer(container);
};
