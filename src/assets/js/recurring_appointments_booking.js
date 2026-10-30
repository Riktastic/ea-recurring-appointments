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
 * - Step "date and time": a "Repeat" select (does not repeat / every day / every week / every 2 weeks / every 4 weeks /
 *   every month / every year) under the available hours; for the weekly patterns extra weekdays; and the end: a number of appointments
 *   or an end date (never more than the administrator's maximum).
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

    const PATTERNS = ['daily', 'weekly', 'every_2_weeks', 'every_4_weeks', 'monthly', 'yearly'];

    function t(key, params = {}) {
        let text = strings['ra_' + key] ?? key;

        Object.keys(params).forEach((name) => {
            text = text.split('{' + name + '}').join(params[name]);
        });

        return text;
    }

    /**
     * HTML-escape a value for use in element content and in quoted attributes (quotes included).
     */
    function escape(value) {
        return String(value ?? '').replace(
            /[&<>"']/g,
            (char) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[char],
        );
    }

    function selectedPattern() {
        const pattern = $('#ra-booking-pattern').val();

        return PATTERNS.includes(pattern) ? pattern : '';
    }

    function selectedCount() {
        const count = Math.round(Number($('#ra-booking-count').val()));

        return Math.min(maxCount, Math.max(2, Number.isFinite(count) ? count : 2));
    }

    function isWeekly(pattern) {
        return pattern === 'weekly' || pattern === 'every_2_weeks' || pattern === 'every_4_weeks';
    }

    function endsOnDate() {
        return $('#ra-booking-end-until').is(':checked') && /^\d{4}-\d{2}-\d{2}$/.test($('#ra-booking-until').val());
    }

    /**
     * The date picked in the booking wizard, as a moment (null until a date is picked).
     */
    function selectedDate() {
        const date = App.Utils.UI.getDateTimePickerValue($('#select-date'));

        return date ? moment(date) : null;
    }

    /**
     * Weekday name in the booking page language ("Mon", "Monday").
     */
    function weekdayName(isoWeekday, style) {
        try {
            return new Intl.DateTimeFormat(config.locale || undefined, {weekday: style}).format(
                new Date(2024, 0, Number(isoWeekday)), // 2024-01-01 was a Monday.
            );
        } catch (error) {
            return moment().isoWeekday(Number(isoWeekday)).format(style === 'short' ? 'ddd' : 'dddd');
        }
    }

    function selectedWeekdays() {
        return $('#ra-booking-weekdays input:checked')
            .map((index, input) => Number(input.dataset.weekday))
            .get();
    }

    function buildRepeatChoice() {
        const options = PATTERNS.map((pattern) => `<option value="${pattern}">${escape(t('booking_' + pattern))}</option>`);

        // Same order as the date picker: start at the configured first weekday.
        const names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        const first = Math.max(0, names.indexOf(String(vars('first_weekday') || 'monday').toLowerCase()));

        const weekdays = [0, 1, 2, 3, 4, 5, 6]
            .map((offset) => ((first + offset) % 7) + 1)
            .map(
                (weekday) => `
                <input type="checkbox" class="btn-check" id="ra-booking-weekday-${weekday}" data-weekday="${weekday}" autocomplete="off">
                <label class="btn btn-outline-secondary btn-sm" for="ra-booking-weekday-${weekday}"
                       title="${escape(weekdayName(weekday, 'long'))}">${escape(weekdayName(weekday, 'short'))}</label>`,
            )
            .join('');

        return $(`
            <div id="ra-booking-repeat" class="mb-3">
                <label class="form-label" for="ra-booking-pattern">${escape(t('booking_repeat'))}</label>
                <select id="ra-booking-pattern" class="form-select">
                    <option value="">${escape(t('booking_no_repeat'))}</option>
                    ${options.join('')}
                </select>
                <div id="ra-booking-options" class="mt-2" hidden>
                    <div id="ra-booking-weekdays-row" class="mb-2">
                        <div class="form-label mb-1">${escape(t('booking_weekdays'))}</div>
                        <div id="ra-booking-weekdays" class="btn-group flex-wrap" role="group">${weekdays}</div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ra-booking-end" id="ra-booking-end-count" checked>
                        <label class="form-check-label" for="ra-booking-end-count">${escape(t('booking_count'))}</label>
                    </div>
                    <input type="number" id="ra-booking-count" class="form-control mb-2" min="2" max="${maxCount}"
                           value="${Math.min(4, maxCount)}" aria-label="${escape(t('booking_count'))}">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ra-booking-end" id="ra-booking-end-until">
                        <label class="form-check-label" for="ra-booking-end-until">${escape(t('booking_until'))}</label>
                    </div>
                    <input type="date" id="ra-booking-until" class="form-control" aria-label="${escape(t('booking_until'))}">
                    <div class="form-text">${escape(t('booking_hint_max', {max: maxCount}))}</div>
                </div>
            </div>
        `);
    }

    /**
     * Keep the weekday of the picked date switched on (that is the first appointment), and the end date after it.
     * It stays a normal, selected-looking button (a disabled one would look unselected); switching it off is undone
     * in the change handler.
     */
    function syncWithSelectedDate() {
        const date = selectedDate();

        if (!date) {
            return;
        }

        $('#ra-booking-weekdays input').each((index, input) => {
            const isFirst = Number(input.dataset.weekday) === date.isoWeekday();

            if (isFirst) {
                input.checked = true;
            }

            input.dataset.locked = isFirst ? '1' : '';
        });

        $('#ra-booking-until').attr('min', date.clone().add(1, 'day').format('YYYY-MM-DD'));
    }

    function updateOptions() {
        const pattern = selectedPattern();

        $('#ra-booking-options').prop('hidden', !pattern);
        $('#ra-booking-weekdays-row').prop('hidden', !isWeekly(pattern));
        syncWithSelectedDate();
        showSummary();
    }

    /**
     * The repeat choice as sent to the server: see Recurring_booking::extend().
     */
    function repeatRequest() {
        const pattern = selectedPattern();
        const repeat = {pattern};

        if (isWeekly(pattern)) {
            repeat.weekdays = selectedWeekdays();
        }

        if (endsOnDate()) {
            repeat.until = $('#ra-booking-until').val();
        } else {
            repeat.count = selectedCount();
        }

        return repeat;
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

        let text = t('booking_' + pattern);

        if (isWeekly(pattern) && selectedWeekdays().length > 1) {
            text += ' (' + selectedWeekdays().sort().map((day) => weekdayName(day, 'long')).join(', ') + ')';
        }

        text = endsOnDate()
            ? t('booking_summary_until', {
                  pattern: text,
                  date: App.Utils.Date.format($('#ra-booking-until').val(), vars('date_format'), vars('time_format'), false),
              })
            : t('booking_summary', {pattern: text, count: selectedCount()});

        $details.children().first().append(
            `<div id="ra-booking-summary" class="mb-2"><span aria-hidden="true">&#8635;</span> ${escape(text)}</div>`,
        );
    }

    function initialize() {
        if (vars('manage_mode') || !$('#available-hours').length || $('#ra-booking-repeat').length) {
            return; // Rescheduling an existing appointment, or not the booking page.
        }

        $('#available-hours').after(buildRepeatChoice());

        // Selected days look like the selected time slot (the page's primary color); some booking page themes make
        // checked outline buttons almost white.
        $('<style>')
            .text(
                '#ra-booking-weekdays .btn-check:checked + .btn {' +
                    ' background-color: var(--bs-primary, #429a82); border-color: var(--bs-primary, #429a82); color: #fff; }',
            )
            .appendTo('head');

        $('#ra-booking-pattern').on('change', updateOptions);
        $('#ra-booking-weekdays').on('change', 'input', (event) => {
            if (event.currentTarget.dataset.locked === '1') {
                event.currentTarget.checked = true; // The day of the first appointment always stays in.
            }

            showSummary();
        });

        $('#ra-booking-count').on('change input focus', () => {
            $('#ra-booking-end-count').prop('checked', true);
            showSummary();
        });

        $('#ra-booking-until').on('change input focus', () => {
            $('#ra-booking-end-until').prop('checked', true);
            showSummary();
        });

        $('input[name="ra-booking-end"]').on('change', showSummary);

        // Keep the number within the allowed range when the customer leaves the field.
        $('#ra-booking-count').on('blur', (event) => $(event.currentTarget).val(selectedCount()));

        // Picking another date or time changes which weekday is the first appointment.
        $('#select-date').on('change', syncWithSelectedDate);
        $('#available-hours').on('click', syncWithSelectedDate);

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

            options.data += '&' + $.param({ra_repeat: repeatRequest()});
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
