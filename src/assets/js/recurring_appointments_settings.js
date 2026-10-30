/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * "Repeating appointments" section on Settings → Booking.
 *
 * The fields carry data-field attributes like the core fields of this page, so the core page script loads them from
 * and saves them to the settings table with its own Save button; nothing else is needed. For that to work they must
 * exist before the core script initializes on DOMContentLoaded, which is why this script inserts them right away
 * instead of waiting for that event (it is loaded at the end of the body, so the page markup is already there).
 */
(function () {
    'use strict';

    const config = window.RecurringAppointmentsConfig || {};
    const strings = config.strings || {};
    const settings = config.settings || {};

    function t(key) {
        return strings['ra_' + key] ?? key;
    }

    /**
     * HTML-escape a value for use in element content and in quoted attributes (quotes included). The setting values
     * end up in value="..." attributes, so quotes must be escaped too.
     */
    function escape(value) {
        return String(value ?? '').replace(
            /[&<>"']/g,
            (char) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[char],
        );
    }

    function toggleSwitch(name, label) {
        const id = 'ra-setting-' + name.replace(/_/g, '-');

        return `
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="${id}" data-field="recurring_appointments_${name}"
                       ${settings[name] === '1' ? 'checked' : ''}>
                <label class="form-check-label" for="${id}">${escape(t(label))}</label>
            </div>`;
    }

    function buildSection() {
        const notify = settings.booking_notify === 'first' ? 'first' : 'all';

        return `
            <div id="ra-settings">
                <h5 class="mb-3 fw-light">${escape(t('settings_title'))}</h5>
                <div class="row">
                    <div class="col-12">
                        <div class="border rounded mb-3 p-3">
                            ${toggleSwitch('staff_enabled', 'settings_staff_enabled')}
                            <div class="ms-4 mb-3">
                                ${toggleSwitch('staff_default_on', 'settings_staff_default_on')}
                                <div class="mt-2" style="max-width: 420px;">
                                    <label class="form-label" for="ra-setting-max-occurrences">${escape(t('settings_max_occurrences'))}</label>
                                    <input type="number" class="form-control" id="ra-setting-max-occurrences"
                                           data-field="recurring_appointments_max_occurrences"
                                           min="2" max="${Number(config.max_occurrences_limit) || 1000}"
                                           value="${escape(settings.max_occurrences)}">
                                    <div class="form-text text-muted">${escape(t('settings_max_occurrences_hint'))}</div>
                                </div>
                            </div>

                            ${toggleSwitch('booking_enabled', 'settings_booking_enabled')}
                            <div class="form-text text-muted mb-3">${escape(t('settings_booking_hint'))}</div>

                            <div class="ms-4" id="ra-settings-booking">
                                <div class="mb-3" style="max-width: 420px;">
                                    <label class="form-label" for="ra-setting-booking-max">${escape(t('settings_booking_max'))}</label>
                                    <input type="number" class="form-control" id="ra-setting-booking-max"
                                           data-field="recurring_appointments_booking_max"
                                           min="2" max="${Number(config.booking_max_limit) || 52}"
                                           value="${escape(settings.booking_max)}">
                                </div>
                                <div class="mb-3" style="max-width: 420px;">
                                    <label class="form-label" for="ra-setting-booking-notify">${escape(t('settings_booking_notify'))}</label>
                                    <select class="form-select" id="ra-setting-booking-notify"
                                            data-field="recurring_appointments_booking_notify">
                                        <option value="all" ${notify === 'all' ? 'selected' : ''}>${escape(t('settings_booking_notify_all'))}</option>
                                        <option value="first" ${notify === 'first' ? 'selected' : ''}>${escape(t('settings_booking_notify_first'))}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-text text-muted">${escape(t('settings_save_hint'))}</div>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    /**
     * Dependent options are only editable while their main switch is on.
     */
    function updateDependentFields() {
        const staffEnabled = document.getElementById('ra-setting-staff-enabled').checked;
        const bookingEnabled = document.getElementById('ra-setting-booking-enabled').checked;

        document.getElementById('ra-setting-staff-default-on').disabled = !staffEnabled;
        document.getElementById('ra-setting-max-occurrences').disabled = !staffEnabled;
        document
            .querySelectorAll('#ra-settings-booking input, #ra-settings-booking select')
            .forEach((field) => (field.disabled = !bookingEnabled));
    }

    const fieldset = document.querySelector('#booking-settings-page form fieldset');

    if (!fieldset || document.getElementById('ra-settings')) {
        return;
    }

    fieldset.insertAdjacentHTML('beforeend', buildSection());

    document.getElementById('ra-settings').addEventListener('change', updateDependentFields);

    // The core fills in the saved values on DOMContentLoaded; update the disabled states after that.
    document.addEventListener('DOMContentLoaded', () => setTimeout(updateDependentFields));
    updateDependentFields();
})();
