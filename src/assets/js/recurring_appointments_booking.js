/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Repeat choice on the public booking page.
 *
 * Only loaded when an administrator switched on "Customers can book a repeating appointment" (Settings → Booking).
 *
 * - Step "date and time": a "Repeat" select (does not repeat / every week / every 2 weeks / every 4 weeks / every
 *   month) and the number of appointments, under the available hours.
 * - Step "confirmation": a line with the chosen repeat under the appointment details.
 * - Booking: the choice is added to the core booking/register request as "ra_repeat". The core books the first
 *   appointment as always; the extension's display hook then books the following dates that are free (see
 *   Recurring_booking), and the confirmation page lists them.
 *
 * Nothing changes when the customer keeps "Does not repeat", or when an existing appointment is rescheduled.
 */
(function () {
    'use strict';

    const config = window.RecurringAppointmentsConfig || {};
    const strings = config.strings || {};
    const maxCount = Math.max(2, Number(config.booking_max) || 2);

    const PATTERNS = ['weekly', 'every_2_weeks', 'every_4_weeks', 'monthly'];

    function t(key, params = {}) {
        let text = strings['ra_' + key] ?? key;

        Object.keys(params).forEach((name) => {
            text = text.split('{' + name + '}').join(params[name]);
        });

        return text;
    }

    function escape(value) {
        return $('<div>').text(value ?? '').html();
    }

    function selectedPattern() {
        const pattern = $('#ra-booking-pattern').val();

        return PATTERNS.includes(pattern) ? pattern : '';
    }

    function selectedCount() {
        const count = Math.round(Number($('#ra-booking-count').val()));

        return Math.min(maxCount, Math.max(2, Number.isFinite(count) ? count : 2));
    }

    function buildRepeatChoice() {
        const options = PATTERNS.map((pattern) => `<option value="${pattern}">${escape(t('booking_' + pattern))}</option>`);

        return $(`
            <div id="ra-booking-repeat" class="mb-3">
                <label class="form-label" for="ra-booking-pattern">${escape(t('booking_repeat'))}</label>
                <select id="ra-booking-pattern" class="form-select">
                    <option value="">${escape(t('booking_no_repeat'))}</option>
                    ${options.join('')}
                </select>
                <div id="ra-booking-count-row" class="mt-2" hidden>
                    <label class="form-label" for="ra-booking-count">${escape(t('booking_count'))}</label>
                    <input type="number" id="ra-booking-count" class="form-control" min="2" max="${maxCount}"
                           value="${Math.min(4, maxCount)}">
                    <div class="form-text">${escape(t('booking_hint'))}</div>
                </div>
            </div>
        `);
    }

    /**
     * Add the chosen repeat to the details on the confirmation step. The core rewrites #appointment-details every time
     * that step is updated, so the line is added again whenever its content changes.
     */
    function showSummary() {
        const $details = $('#appointment-details');

        $details.find('#ra-booking-summary').remove();

        const pattern = selectedPattern();

        if (!pattern || !$details.children().length) {
            return;
        }

        const text = t('booking_summary', {pattern: t('booking_' + pattern), count: selectedCount()});

        $details.children().first().append(
            `<div id="ra-booking-summary" class="mb-2"><span aria-hidden="true">&#8635;</span> ${escape(text)}</div>`,
        );
    }

    function initialize() {
        if (vars('manage_mode') || !$('#available-hours').length || $('#ra-booking-repeat').length) {
            return; // Rescheduling an existing appointment, or not the booking page.
        }

        $('#available-hours').after(buildRepeatChoice());

        $('#ra-booking-pattern').on('change', () => {
            $('#ra-booking-count-row').prop('hidden', !selectedPattern());
            showSummary();
        });

        $('#ra-booking-count').on('change input', showSummary);

        // Keep the number within the allowed range when the customer leaves the field.
        $('#ra-booking-count').on('blur', (event) => $(event.currentTarget).val(selectedCount()));

        const details = document.getElementById('appointment-details');

        if (details && window.MutationObserver) {
            new MutationObserver(() => {
                if (!document.getElementById('ra-booking-summary')) {
                    showSummary();
                }
            }).observe(details, {childList: true});
        }

        // Add the choice to the core booking request (the request data is already a query string at this point).
        $.ajaxPrefilter((options) => {
            const pattern = selectedPattern();

            if (!pattern || !String(options.url || '').includes('booking/register') || typeof options.data !== 'string') {
                return;
            }

            options.data += '&' + $.param({ra_repeat: {pattern, count: selectedCount()}});
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
