/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Recurring appointments UI.
 *
 * Loaded on the backend calendar page by the display_override hook, which also provides
 * window.RecurringAppointmentsConfig (translated strings, install state). It does not modify core scripts:
 *
 * - A "Repeat" section is added to the appointment modal for new appointments. When it is switched on, a capturing
 *   click listener takes over the Save button and posts to recurring_appointments/create instead.
 * - When an existing appointment that belongs to a series is opened, a series panel offers "save for this and
 *   following / all", "detach" and "delete" actions. The regular Save button keeps saving just that occurrence.
 * - Appointments that belong to a series get a ↻ marker on the calendar, and their pop-up names the series.
 *
 * Localization: the extension strings come from its own language files (English fallback per key). Weekday names and
 * generic words (Save, Cancel, Delete) come from the core translations, so they exist in every Easy!Appointments
 * language. Dates and times follow the configured date format, time format and first weekday.
 */
(function () {
    'use strict';

    const config = window.RecurringAppointmentsConfig || {};
    const strings = config.strings || {};
    const settings = config.settings || {};

    // ISO weekday number (1 = Monday) to the core translation key.
    const WEEKDAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Extension string (key without the "ra_" prefix) with {placeholders}.
     */
    function t(key, params = {}) {
        let text = strings['ra_' + key] ?? key;

        Object.keys(params).forEach((name) => {
            text = text.split('{' + name + '}').join(params[name]);
        });

        return text;
    }

    /**
     * Extension string with a count: uses the "<key>_one" variant for exactly 1 ("1 appointment deleted.").
     */
    function tCount(key, count) {
        const singular = 'ra_' + key + '_one';

        return Number(count) === 1 && strings[singular] ? strings[singular] : t(key, {[key.split('_').pop()]: count});
    }

    /**
     * Core translation with a fallback, for keys that might not exist in every Easy!Appointments release.
     */
    function coreLang(key, fallback) {
        try {
            const all = typeof window.lang === 'function' ? window.lang() : {};

            return (all && all[key]) || fallback;
        } catch (error) {
            return fallback;
        }
    }

    function weekdayName(isoWeekday) {
        const key = WEEKDAY_KEYS[Number(isoWeekday) - 1];

        return coreLang(key, key.charAt(0).toUpperCase() + key.slice(1));
    }

    /**
     * Weekday name from the browser's locale data, which also gets the casing of the language right inside a
     * sentence ("vrijdag" in Dutch, "Freitag" in German). Falls back to the core translation.
     */
    function localeWeekday(isoWeekday, style) {
        // 2024-01-01 was a Monday.
        const date = new Date(2024, 0, Number(isoWeekday));

        try {
            return new Intl.DateTimeFormat(config.locale || undefined, {weekday: style}).format(date);
        } catch (error) {
            return style === 'short' ? weekdayName(isoWeekday).substring(0, 3) : weekdayName(isoWeekday);
        }
    }

    function weekdayShortName(isoWeekday) {
        return localeWeekday(isoWeekday, 'short');
    }

    /**
     * ISO weekdays in display order, starting with the configured first weekday.
     */
    function orderedWeekdays() {
        const first = WEEKDAY_KEYS.indexOf(String(vars('first_weekday') || 'monday').toLowerCase());
        const start = first === -1 ? 0 : first;

        return [0, 1, 2, 3, 4, 5, 6].map((offset) => ((start + offset) % 7) + 1);
    }

    function formatDate(value, withHours = false) {
        try {
            return App.Utils.Date.format(
                moment(value).toDate(),
                vars('date_format') || 'YMD',
                vars('time_format') || 'military',
                withHours,
            );
        } catch (error) {
            return moment(value).format(withHours ? 'YYYY-MM-DD HH:mm' : 'YYYY-MM-DD');
        }
    }

    function formatTime(value) {
        const time = moment(value, ['HH:mm', 'HH:mm:ss']);

        return time.isValid() ? time.format(vars('time_format') === 'regular' ? 'h:mm a' : 'HH:mm') : value;
    }

    function escape(value) {
        return $('<div>').text(value ?? '').html();
    }

    function can(action) {
        const privileges = vars('privileges') || {};

        return Boolean(privileges.appointments && privileges.appointments[action]);
    }

    function post(path, data) {
        return $.post(App.Utils.Url.siteUrl(path), Object.assign({csrf_token: vars('csrf_token')}, data));
    }

    /**
     * Show an error from a failed request (jqXHR) or an unsuccessful JSON response in plain words.
     *
     * Messages from the extension are already translated on the server. Responses without a JSON message (an expired
     * session redirects to the login page, a forbidden request gets an HTML error page, a PHP error gets a blank or
     * HTML page) get a translated explanation of what to do instead of a technical text.
     */
    function showError(response) {
        const status = response?.status;
        let message = response?.responseJSON?.message || (typeof response?.message === 'string' ? response.message : '');

        if (!message) {
            if (status === 401 || /name="password"|id="login/i.test(response?.responseText || '')) {
                message = t('error_session');
            } else if (status === 403) {
                message = t('error_permission');
            } else {
                message = t('error_server');
            }

            App.Utils.Message.show(escape(t('title')), escape(message));
            return;
        }

        App.Utils.Message.show(escape(t('title')), escape(t('error_generic', {message})));
    }

    function notify(message) {
        if (App.Layouts?.Backend?.displayNotification) {
            App.Layouts.Backend.displayNotification(message);
        }
    }

    function reloadCalendar() {
        $('#reload-appointments').trigger('click');
    }

    // ------------------------------------------------------------------------------------------------------------
    // Modal data
    // ------------------------------------------------------------------------------------------------------------

    function getModalAppointment() {
        const start = App.Utils.UI.getDateTimePickerValue($('#start-datetime'));
        const end = App.Utils.UI.getDateTimePickerValue($('#end-datetime'));

        const appointment = {
            id_services: $('#select-service').val(),
            id_users_provider: $('#select-provider').val(),
            start_datetime: moment(start).format('YYYY-MM-DD HH:mm:ss'),
            end_datetime: moment(end).format('YYYY-MM-DD HH:mm:ss'),
            location: $('#appointment-location').val(),
            meeting_link: $('#appointment-meeting-link').val(),
            color: App.Components.ColorSelection
                ? App.Components.ColorSelection.getColor($('#appointment-color'))
                : '',
            status: $('#appointment-status').val(),
            notes: $('#appointment-notes').val(),
            is_unavailability: 0,
        };

        if ($('#appointment-id').val() !== '') {
            appointment.id = $('#appointment-id').val();
        }

        if ($('#customer-id').val() !== '') {
            appointment.id_users_customer = $('#customer-id').val();
        }

        return appointment;
    }

    function getModalCustomer() {
        const customer = {
            first_name: $('#first-name').val(),
            last_name: $('#last-name').val(),
            email: $('#email').val(),
            phone_number: $('#phone-number').val(),
            address: $('#address').val(),
            city: $('#city').val(),
            zip_code: $('#zip-code').val(),
            language: $('#language').val(),
            timezone: $('#timezone').val(),
            notes: $('#customer-notes').val(),
        };

        for (let index = 1; index <= 5; index++) {
            const $field = $('#custom-field-' + index);

            if ($field.length) {
                customer['custom_field_' + index] = $field.val();
            }
        }

        if ($('#customer-id').val() !== '') {
            customer.id = $('#customer-id').val();
        }

        return customer;
    }

    // ------------------------------------------------------------------------------------------------------------
    // Repeat section (new appointments)
    // ------------------------------------------------------------------------------------------------------------

    function nthLabel(nth) {
        return t(String(nth) === '-1' ? 'nth_last' : 'nth_' + nth);
    }

    function buildRepeatSection() {
        const weekdayChecks = orderedWeekdays()
            .map(
                (weekday) => `
                <input type="checkbox" class="btn-check" id="ra-weekday-${weekday}" data-weekday="${weekday}" autocomplete="off">
                <label class="btn btn-outline-secondary btn-sm" for="ra-weekday-${weekday}" title="${escape(weekdayName(weekday))}">${escape(weekdayShortName(weekday))}</label>`,
            )
            .join('');

        const weekdayOptions = orderedWeekdays()
            .map((weekday) => `<option value="${weekday}">${escape(weekdayName(weekday))}</option>`)
            .join('');

        const nthOptions = [1, 2, 3, 4, 5, -1]
            .map((value) => `<option value="${value}">${escape(nthLabel(value))}</option>`)
            .join('');

        return $(`
            <div id="ra-repeat-section" class="border rounded p-3 mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="ra-enabled">
                    <label class="form-check-label fw-bold" for="ra-enabled">${escape(t('repeat_this'))}</label>
                </div>
                <div id="ra-options" class="mt-3" style="display: none;">
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-sm-7">
                            <select id="ra-frequency" class="form-select form-select-sm" aria-label="${escape(t('repeat'))}">
                                <option value="weekly">${escape(t('frequency_weekly'))}</option>
                                <option value="monthly_weekday">${escape(t('frequency_monthly_weekday'))}</option>
                                <option value="monthly_day">${escape(t('frequency_monthly_day'))}</option>
                            </select>
                        </div>
                        <div class="col-sm-5 d-flex align-items-center gap-2">
                            <label for="ra-interval" class="mb-0">${escape(t('every'))}</label>
                            <input id="ra-interval" type="number" min="1" max="52" value="1" class="form-control form-control-sm" style="width: 5em;">
                            <span id="ra-interval-unit">${escape(t('weeks'))}</span>
                        </div>
                    </div>
                    <div id="ra-weekly" class="mb-2">
                        <span class="me-2">${escape(t('on'))}</span>
                        <div class="btn-group flex-wrap" role="group">${weekdayChecks}</div>
                    </div>
                    <div id="ra-monthly-weekday" class="mb-2 d-flex align-items-center gap-2">
                        <span>${escape(t('the'))}</span>
                        <select id="ra-nth" class="form-select form-select-sm" style="width: auto;">${nthOptions}</select>
                        <select id="ra-weekday" class="form-select form-select-sm" style="width: auto;">${weekdayOptions}</select>
                    </div>
                    <div id="ra-monthly-day" class="mb-2 d-flex align-items-center gap-2">
                        <label for="ra-day" class="mb-0">${escape(t('day'))}</label>
                        <input id="ra-day" type="number" min="1" max="31" value="1" class="form-control form-control-sm" style="width: 5em;">
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span>${escape(t('ends'))}</span>
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input" type="radio" name="ra-end" id="ra-end-count" checked>
                            <label class="form-check-label" for="ra-end-count">${escape(t('after'))}</label>
                        </div>
                        <input id="ra-count" type="number" min="1" max="260" value="10" class="form-control form-control-sm" style="width: 5em;">
                        <span>${escape(t('occurrences'))}</span>
                        <div class="form-check form-check-inline m-0 ms-3">
                            <input class="form-check-input" type="radio" name="ra-end" id="ra-end-until">
                            <label class="form-check-label" for="ra-end-until">${escape(t('on_date'))}</label>
                        </div>
                        <div style="width: 10em;"><input id="ra-until" class="form-control form-control-sm"></div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <label for="ra-notify" class="mb-0">${escape(t('notify'))}</label>
                        <select id="ra-notify" class="form-select form-select-sm" style="width: auto;">
                            <option value="first">${escape(t('notify_first'))}</option>
                            <option value="none">${escape(t('notify_none'))}</option>
                            <option value="all">${escape(t('notify_all'))}</option>
                        </select>
                        <button type="button" id="ra-preview" class="btn btn-outline-secondary btn-sm ms-auto">${escape(t('preview'))}</button>
                    </div>
                    <div id="ra-summary" class="form-text mt-2"></div>
                </div>
            </div>
        `);
    }

    // Some blocks use the d-flex class, which can only be hidden with !important.
    function toggleBlock($element, visible) {
        if (visible) {
            $element[0].style.removeProperty('display');
        } else {
            $element[0].style.setProperty('display', 'none', 'important');
        }
    }

    function getUntilDate() {
        const $until = $('#ra-until');

        if ($until[0]?._flatpickr) {
            const date = App.Utils.UI.getDateTimePickerValue($until);

            return date ? moment(date).format('YYYY-MM-DD') : '';
        }

        return $until.val();
    }

    // True while the code (not the user) changes the end date, so that the change does not select "On date".
    let settingUntilDate = false;

    function setUntilDate(date) {
        const $until = $('#ra-until');

        if ($until[0]?._flatpickr) {
            settingUntilDate = true;

            try {
                if (date) {
                    App.Utils.UI.setDateTimePickerValue($until, date);
                } else {
                    $until[0]._flatpickr.clear();
                }
            } finally {
                settingUntilDate = false;
            }

            return;
        }

        $until.val(date ? moment(date).format('YYYY-MM-DD') : '');
    }

    function updateRepeatVisibility() {
        const frequency = $('#ra-frequency').val();

        toggleBlock($('#ra-weekly'), frequency === 'weekly');
        toggleBlock($('#ra-monthly-weekday'), frequency === 'monthly_weekday');
        toggleBlock($('#ra-monthly-day'), frequency === 'monthly_day');
        $('#ra-interval-unit').text(frequency === 'weekly' ? t('weeks') : t('months'));
        $('#ra-options').toggle($('#ra-enabled').is(':checked'));
        $('#ra-summary').text(describeRule(getRule()));
    }

    /**
     * Fill the weekday / week-of-month / day controls from the start date of the modal.
     */
    function applyStartDateDefaults() {
        const start = App.Utils.UI.getDateTimePickerValue($('#start-datetime'));

        if (!start) {
            return;
        }

        const date = moment(start);
        const isoWeekday = date.isoWeekday();
        const week = Math.ceil(date.date() / 7);

        $('#ra-repeat-section input[data-weekday]').prop('checked', false);
        $('#ra-weekday-' + isoWeekday).prop('checked', true);
        $('#ra-weekday').val(isoWeekday);
        // The 5th weekday does not exist in every month, "last" is what people usually mean.
        $('#ra-nth').val(week === 5 ? -1 : week);
        $('#ra-day').val(date.date());

        if (!getUntilDate()) {
            setUntilDate(date.clone().add(3, 'months').toDate());
        }

        updateRepeatVisibility();
    }

    function getRule() {
        const frequency = $('#ra-frequency').val();
        const rule = {
            frequency,
            interval: $('#ra-interval').val() || 1,
        };

        if (frequency === 'weekly') {
            rule.weekdays = $('#ra-repeat-section input[data-weekday]:checked')
                .map((index, input) => input.dataset.weekday)
                .get();
        } else if (frequency === 'monthly_weekday') {
            rule.nth = $('#ra-nth').val();
            rule.weekday = $('#ra-weekday').val();
        } else {
            rule.day = $('#ra-day').val();
        }

        if ($('#ra-end-until').is(':checked')) {
            rule.until = getUntilDate();
        } else {
            rule.count = $('#ra-count').val();
        }

        return rule;
    }

    /**
     * Readable rule. Leave out the count where the actual number of occurrences is shown next to it, since skipped or
     * deleted dates make the two differ.
     */
    function describeRule(rule, withCount = true) {
        if (!rule || !rule.frequency) {
            return '';
        }

        // "every week" instead of "every 1 week(s)".
        const suffix = Number(rule.interval || 1) === 1 ? '_one' : '';

        let text;

        if (rule.frequency === 'weekly') {
            const days = (rule.weekdays || []).map((day) => localeWeekday(day, 'long')).join(', ');
            text = t('summary_weekly' + suffix, {interval: rule.interval, days});
        } else if (rule.frequency === 'monthly_weekday') {
            text = t('summary_monthly_weekday' + suffix, {
                interval: rule.interval,
                nth: nthLabel(rule.nth),
                weekday: localeWeekday(rule.weekday, 'long'),
            });
        } else {
            text = t('summary_monthly_day' + suffix, {interval: rule.interval, day: rule.day});
        }

        if (rule.until) {
            text += ', ' + t('summary_until', {date: formatDate(rule.until)});
        } else if (rule.count && withCount) {
            text += ', ' + t('summary_count', {count: rule.count});
        }

        return text;
    }

    /**
     * Translated conflict reason. Falls back to the English message from the server.
     */
    function conflictText(conflict) {
        if (!conflict.key || !strings['ra_' + conflict.key]) {
            return conflict.message;
        }

        const params = Object.assign({}, conflict.params || {});

        ['start', 'end'].forEach((name) => {
            if (params[name]) {
                params[name] = formatTime(params[name]);
            }
        });

        return t(conflict.key, params);
    }

    function occurrencesHtml(occurrences) {
        const items = occurrences
            .map((occurrence) => {
                const conflicts = occurrence.conflicts || [];
                const badge = conflicts.length
                    ? `<span class="text-danger">&#9888; ${conflicts.map((c) => escape(conflictText(c))).join(' ')}</span>`
                    : '<span class="text-success">&#10003;</span>';
                const label =
                    weekdayShortName(moment(occurrence.start_datetime).isoWeekday()) +
                    ' ' +
                    formatDate(occurrence.start_datetime, true);

                return `<li class="${conflicts.length ? 'fw-bold' : ''}">${escape(label)} ${badge}</li>`;
            })
            .join('');

        return `<ul class="small mb-0" style="max-height: 50vh; overflow-y: auto;">${items}</ul>`;
    }

    /**
     * Show occurrences + conflicts. With onDecision, offers skip / force buttons.
     */
    function showOccurrences(response, onDecision) {
        const occurrences = response.occurrences || [];
        const conflictCount = occurrences.filter((o) => (o.conflicts || []).length).length;

        const intro = conflictCount
            ? t('conflicts_intro', {count: conflictCount, total: occurrences.length})
            : t('no_conflicts', {total: occurrences.length});

        const buttons = onDecision
            ? [
                  {text: escape(coreLang('cancel', 'Cancel')), click: (event, modal) => modal.hide()},
                  {
                      text: escape(t('book_anyway')),
                      className: 'btn btn-outline-danger',
                      click: (event, modal) => {
                          modal.hide();
                          onDecision('force');
                      },
                  },
                  {
                      text: escape(t('skip_conflicts')),
                      click: (event, modal) => {
                          modal.hide();
                          onDecision('skip');
                      },
                  },
              ]
            : null;

        App.Utils.Message.show(
            escape(conflictCount ? coreLang('scheduling_conflict', t('title')) : t('title')),
            `<p>${escape(intro)}</p>${occurrencesHtml(occurrences)}`,
            buttons,
        );
    }

    function previewSeries() {
        if (!App.Components.AppointmentsModal.validateAppointmentForm()) {
            return;
        }

        post('recurring_appointments/preview', {
            appointment_data: getModalAppointment(),
            rule: getRule(),
        })
            .done((response) => (response.success ? showOccurrences(response) : showError(response)))
            .fail(showError);
    }

    function createSeries(conflictMode = 'abort') {
        post('recurring_appointments/create', {
            appointment_data: getModalAppointment(),
            customer_data: getModalCustomer(),
            rule: getRule(),
            conflict_mode: conflictMode,
            notify: $('#ra-notify').val(),
        })
            .done((response) => {
                if (response.conflict) {
                    showOccurrences(response, (mode) => createSeries(mode));
                    return;
                }

                if (!response.success) {
                    showError(response);
                    return;
                }

                let message = tCount('series_created', response.created);

                if (response.skipped && response.skipped.length) {
                    message += ' ' + tCount('series_skipped', response.skipped.length);
                }

                notify(message);
                $('#appointments-modal').modal('hide');
                reloadCalendar();
            })
            .fail(showError);
    }

    // ------------------------------------------------------------------------------------------------------------
    // Series panel (existing occurrences)
    // ------------------------------------------------------------------------------------------------------------

    function buildSeriesPanel(series) {
        const editButtons = can('edit')
            ? `
                <button type="button" class="btn btn-sm btn-primary" data-ra-save="following">${escape(t('save_following'))}</button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-ra-save="all">${escape(t('save_all'))}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-ra-detach>${escape(t('detach'))}</button>`
            : '';

        const deleteMenu = can('delete')
            ? `
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown">${escape(coreLang('delete', 'Delete'))}</button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-ra-delete="single">${escape(t('delete_single'))}</a></li>
                        <li><a class="dropdown-item" href="#" data-ra-delete="following">${escape(t('delete_following'))}</a></li>
                        <li><a class="dropdown-item text-danger" href="#" data-ra-delete="all">${escape(t('delete_all'))}</a></li>
                    </ul>
                </div>`
            : '';

        const notifySelect =
            editButtons || deleteMenu
                ? `
                <select class="form-select form-select-sm ms-auto" data-ra-notify style="width: auto;" aria-label="${escape(t('notify'))}">
                    <option value="none">${escape(t('notify'))}: ${escape(t('notify_none'))}</option>
                    <option value="first">${escape(t('notify'))}: ${escape(t('notify_first'))}</option>
                    <option value="all">${escape(t('notify'))}: ${escape(t('notify_all'))}</option>
                </select>`
                : '';

        return $(`
            <div id="ra-series-panel" class="alert alert-info mb-3">
                <div class="mb-2">
                    &#8635; ${escape(
                        t('series_info', {
                            position: series.position ?? '?',
                            total: series.total,
                            summary: describeRule(series.rule, false),
                        }),
                    )}
                    ${can('edit') ? `<span class="text-muted">${escape(t('series_hint'))}</span>` : ''}
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">${editButtons}${deleteMenu}${notifySelect}</div>
            </div>
        `);
    }

    function seriesNotifyMode() {
        return $('#ra-series-panel [data-ra-notify]').val() || 'none';
    }

    function saveSeries(scope, conflictMode = 'abort') {
        if (!App.Components.AppointmentsModal.validateAppointmentForm()) {
            return;
        }

        post('recurring_appointments/update', {
            appointment_data: getModalAppointment(),
            customer_data: getModalCustomer(),
            scope,
            conflict_mode: conflictMode,
            notify: seriesNotifyMode(),
        })
            .done((response) => {
                if (response.conflict) {
                    showOccurrences(response, (mode) => saveSeries(scope, mode));
                    return;
                }

                if (!response.success) {
                    showError(response);
                    return;
                }

                let message = tCount('series_updated', response.updated);

                if (response.skipped && response.skipped.length) {
                    message += ' ' + tCount('series_skipped', response.skipped.length);
                }

                notify(message);
                $('#appointments-modal').modal('hide');
                reloadCalendar();
            })
            .fail(showError);
    }

    function deleteSeries(scope) {
        const labels = {single: t('delete_single'), following: t('delete_following'), all: t('delete_all')};

        App.Utils.Message.show(escape(t('title')), escape(t('confirm_delete', {scope: labels[scope]})), [
            {text: escape(coreLang('cancel', 'Cancel')), click: (event, modal) => modal.hide()},
            {
                text: escape(labels[scope]),
                className: 'btn btn-danger',
                click: (event, modal) => {
                    modal.hide();

                    post('recurring_appointments/delete', {
                        appointment_id: $('#appointment-id').val(),
                        scope,
                        notify: seriesNotifyMode(),
                    })
                        .done((response) => {
                            if (!response.success) {
                                showError(response);
                                return;
                            }

                            notify(tCount('series_deleted', response.deleted));
                            $('#appointments-modal').modal('hide');
                            reloadCalendar();
                        })
                        .fail(showError);
                },
            },
        ]);
    }

    function detachOccurrence() {
        post('recurring_appointments/detach', {appointment_id: $('#appointment-id').val()})
            .done((response) => {
                if (!response.success) {
                    showError(response);
                    return;
                }

                notify(t('detached'));
                $('#ra-series-panel').remove();
            })
            .fail(showError);
    }

    function loadSeriesPanel(appointmentId) {
        $('#ra-series-panel').remove();

        if (!appointmentId || !config.installed) {
            return;
        }

        post('recurring_appointments/info', {appointment_id: appointmentId}).done((response) => {
            if (!response.success || !response.series || $('#appointment-id').val() !== String(appointmentId)) {
                return;
            }

            $('#appointments-modal .modal-body').prepend(buildSeriesPanel(response.series));
        });
    }

    // ------------------------------------------------------------------------------------------------------------
    // Calendar markers: a ↻ icon on every appointment of a series, and the series line in the pop-up
    // ------------------------------------------------------------------------------------------------------------

    // Appointment ID => series ID, for the appointments currently on the calendar.
    let seriesIds = {};

    // Appointment ID => mounted event elements (one appointment can span several day columns).
    const mountedEvents = new Map();

    let markerTimer = null;

    function addMarkerStyles() {
        $('<style id="ra-styles">')
            .text(
                '.ra-series-icon { font-weight: bold; margin-right: .25em; }' +
                    '.ra-popover-series { font-size: .875em; margin-bottom: .5rem; }',
            )
            .appendTo('head');
    }

    function setMarker(element, marked) {
        $(element).toggleClass('ra-series', marked);
        $(element).find('.ra-series-icon').remove();

        if (!marked) {
            return;
        }

        const $icon = $('<span class="ra-series-icon">')
            .attr('title', t('series_marker'))
            .append('<span aria-hidden="true">&#8635;</span>')
            .append($('<span class="visually-hidden">').text(t('series_marker')));

        const $title = $(element).find('.fc-event-title').first();

        ($title.length ? $title : $(element).find('.fc-event-main').first()).prepend($icon);
    }

    function refreshMarkers(calendar) {
        const ids = [...new Set(calendar.getEvents().map((event) => event.id).filter(Boolean))];

        if (!ids.length) {
            seriesIds = {};
            return;
        }

        post('recurring_appointments/markers', {appointment_ids: ids}).done((response) => {
            if (!response.success) {
                return;
            }

            seriesIds = response.series || {};

            mountedEvents.forEach((elements, id) => elements.forEach((element) => setMarker(element, Boolean(seriesIds[id]))));
        });
    }

    function initializeMarkers(attempt = 0) {
        const calendar = $('#calendar').data('fullCalendar');

        if (!calendar || typeof calendar.setOption !== 'function') {
            // The default calendar view is created on DOMContentLoaded as well; the table view has no FullCalendar.
            if (attempt < 20) {
                setTimeout(() => initializeMarkers(attempt + 1), 250);
            }

            return;
        }

        addMarkerStyles();

        calendar.setOption('eventDidMount', (info) => {
            const id = info.event.id;

            if (!id) {
                return;
            }

            info.el.dataset.raAppointmentId = id;

            if (!mountedEvents.has(id)) {
                mountedEvents.set(id, new Set());
            }

            mountedEvents.get(id).add(info.el);
            setMarker(info.el, Boolean(seriesIds[id]));
        });

        calendar.setOption('eventWillUnmount', (info) => {
            mountedEvents.get(info.event.id)?.delete(info.el);
        });

        // Fires after every load of the calendar (and after drag and drop); one request per change.
        calendar.on('eventsSet', () => {
            clearTimeout(markerTimer);
            markerTimer = setTimeout(() => refreshMarkers(calendar), 100);
        });

        refreshMarkers(calendar);

        // The core pop-up of an appointment: add "Occurrence 2 of 10 (every Wednesday …)".
        $(document).on('shown.bs.popover', (event) => {
            const id = event.target?.dataset?.raAppointmentId;

            if (!id || !seriesIds[id]) {
                return;
            }

            post('recurring_appointments/info', {appointment_id: id}).done((response) => {
                const series = response.series;
                const $body = $('#calendar .popover .popover-body').last();

                if (!response.success || !series || !$body.length || $body.find('.ra-popover-series').length) {
                    return;
                }

                $body.prepend(
                    $('<div class="ra-popover-series">').text(
                        '↻ ' +
                            t('series_info', {
                                position: series.position ?? '?',
                                total: series.total,
                                summary: describeRule(series.rule, false),
                            }),
                    ),
                );
            });
        });
    }

    // ------------------------------------------------------------------------------------------------------------
    // Setup banner (tables missing)
    // ------------------------------------------------------------------------------------------------------------

    function showInstallBanner() {
        const $banner = $(`
            <div id="ra-install-banner" class="alert alert-warning d-flex flex-wrap align-items-center gap-2 m-3">
                <span>${escape(t('not_installed'))}</span>
                ${config.can_install ? `<button type="button" class="btn btn-sm btn-warning ms-auto">${escape(t('install_now'))}</button>` : ''}
            </div>
        `);

        $banner.find('button').on('click', () => {
            post('recurring_appointments/install', {})
                .done((response) => {
                    if (!response.success) {
                        showError(response);
                        return;
                    }

                    notify(response.message || t('installed'));
                    window.location.reload();
                })
                .fail(showError);
        });

        $('#calendar-page, main, body').first().prepend($banner);
    }

    // ------------------------------------------------------------------------------------------------------------
    // Wiring
    // ------------------------------------------------------------------------------------------------------------

    function isRepeatActive() {
        return config.installed && $('#appointment-id').val() === '' && $('#ra-enabled').is(':checked');
    }

    function initialize() {
        const $modal = $('#appointments-modal');

        if (!$modal.length) {
            return;
        }

        if (!config.installed) {
            // Only admins can act on this, everyone else keeps the calendar as it was.
            if (config.can_install) {
                showInstallBanner();
            }

            return;
        }

        initializeMarkers();

        // Settings → Booking: "Staff can create repeating appointments". Existing series stay manageable either way.
        if (can('add') && settings.staff_enabled !== '0') {
            // Place the repeat section right after the row with the start/end date time pickers.
            const $repeat = buildRepeatSection();
            const $anchor = $('#end-datetime').closest('.row');

            if ($anchor.length) {
                $anchor.after($repeat);
            } else {
                $modal.find('.modal-body').prepend($repeat);
            }

            if (App.Utils.UI?.initializeDatePicker && window.flatpickr) {
                App.Utils.UI.initializeDatePicker($('#ra-until'), {
                    onChange: () => {
                        if (!settingUntilDate) {
                            $('#ra-end-until').prop('checked', true);
                        }

                        updateRepeatVisibility();
                    },
                });
            } else {
                $('#ra-until').attr('type', 'date');
            }

            $modal.on('change', '#ra-enabled', () => {
                if ($('#ra-enabled').is(':checked')) {
                    applyStartDateDefaults();
                }

                updateRepeatVisibility();
            });

            $modal.on('change input', '#ra-repeat-section select, #ra-repeat-section input:not(#ra-enabled)', () =>
                updateRepeatVisibility(),
            );

            $modal.on('focus click', '#ra-until', () => $('#ra-end-until').prop('checked', true));
            $modal.on('focus click', '#ra-count', () => $('#ra-end-count').prop('checked', true));

            $('#start-datetime').on('change', () => {
                if ($('#ra-enabled').is(':checked')) {
                    applyStartDateDefaults();
                }
            });

            $modal.on('click', '#ra-preview', previewSeries);

            // The core resetModal() empties every input of the modal, ours included, so restore the defaults here.
            $modal.on('show.bs.modal', (event) => {
                if (event.target !== $modal[0]) {
                    return; // Nested dropdowns and tooltips fire their own show events.
                }

                $('#ra-enabled').prop('checked', false);
                $('#ra-frequency').val('weekly');
                $('#ra-interval').val(1);
                $('#ra-count').val(10);
                $('#ra-end-count').prop('checked', true);
                setUntilDate(null);
            });

            // Capturing listener: runs before the core jQuery handler on the Save button and takes over only when
            // the repeat switch is on, so the core behavior stays untouched otherwise.
            document.addEventListener(
                'click',
                (event) => {
                    if (!event.target.closest || !event.target.closest('#save-appointment') || !isRepeatActive()) {
                        return;
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    if (!App.Components.AppointmentsModal.validateAppointmentForm()) {
                        return;
                    }

                    createSeries('abort');
                },
                true,
            );
        }

        $modal.on('click', '[data-ra-save]', (event) => saveSeries($(event.currentTarget).data('ra-save')));
        $modal.on('click', '[data-ra-detach]', detachOccurrence);
        $modal.on('click', '[data-ra-delete]', (event) => {
            event.preventDefault();
            deleteSeries($(event.currentTarget).data('ra-delete'));
        });

        $modal.on('show.bs.modal', (event) => {
            if (event.target === $modal[0]) {
                $('#ra-series-panel').remove();
            }
        });

        $modal.on('shown.bs.modal', (event) => {
            if (event.target !== $modal[0]) {
                return;
            }

            const appointmentId = $('#appointment-id').val();

            $('#ra-repeat-section').toggle(appointmentId === '');

            // Settings → Booking: "Switch Repeat on by default". Done here, after the core filled in the start date.
            if (appointmentId === '' && settings.staff_default_on === '1' && $('#ra-enabled').length) {
                $('#ra-enabled').prop('checked', true);
                applyStartDateDefaults();
            }

            if ($('#ra-repeat-section').length) {
                updateRepeatVisibility();
            }

            loadSeriesPanel(appointmentId);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
