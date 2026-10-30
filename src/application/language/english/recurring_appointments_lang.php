<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * English strings, also the fallback for every missing key or language.
 *
 * To translate: copy this file to application/language/<language>/recurring_appointments_lang.php (the folder name
 * Easy!Appointments uses, e.g. "italian") and translate the values. Placeholders like {count} must stay as they are.
 * Weekday names and generic words (Save, Cancel, Delete, ...) come from the Easy!Appointments core translations.
 * ---------------------------------------------------------------------------- */

// Modal: repeat section.
$lang['ra_repeat'] = 'Repeat';
$lang['ra_repeat_this'] = 'Repeat this appointment';
$lang['ra_frequency_daily'] = 'Daily';
$lang['ra_frequency_yearly'] = 'Yearly (same date)';
$lang['ra_frequency_weekly'] = 'Weekly';
$lang['ra_frequency_monthly_weekday'] = 'Monthly on a weekday (e.g. first Monday)';
$lang['ra_frequency_monthly_day'] = 'Monthly on a day number (e.g. the 15th)';
$lang['ra_every'] = 'Every';
$lang['ra_weeks'] = 'week(s)';
$lang['ra_months'] = 'month(s)';
$lang['ra_days'] = 'day(s)';
$lang['ra_years'] = 'year(s)';
$lang['ra_on'] = 'On';
$lang['ra_the'] = 'The';
$lang['ra_day'] = 'Day';
$lang['ra_ends'] = 'Ends';
$lang['ra_after'] = 'After';
$lang['ra_occurrences'] = 'occurrences';
$lang['ra_on_date'] = 'On date';
$lang['ra_nth_1'] = 'first';
$lang['ra_nth_2'] = 'second';
$lang['ra_nth_3'] = 'third';
$lang['ra_nth_4'] = 'fourth';
$lang['ra_nth_5'] = 'fifth';
$lang['ra_nth_last'] = 'last';
$lang['ra_preview'] = 'Preview dates';
$lang['ra_notify'] = 'Notify customer and provider';
$lang['ra_notify_none'] = 'No';
$lang['ra_notify_first'] = 'Once (first occurrence)';
$lang['ra_notify_all'] = 'For every occurrence';

// Rule summaries.
$lang['ra_summary_weekly'] = 'every {interval} week(s) on {days}';
$lang['ra_summary_monthly_weekday'] = 'every {interval} month(s) on the {nth} {weekday}';
$lang['ra_summary_monthly_day'] = 'every {interval} month(s) on day {day}';
$lang['ra_summary_weekly_one'] = 'every week on {days}';
$lang['ra_summary_monthly_weekday_one'] = 'every month on the {nth} {weekday}';
$lang['ra_summary_monthly_day_one'] = 'every month on day {day}';
$lang['ra_summary_daily'] = 'every {interval} days';
$lang['ra_summary_daily_one'] = 'every day';
$lang['ra_summary_yearly'] = 'every {interval} years on {date}';
$lang['ra_summary_yearly_one'] = 'every year on {date}';
$lang['ra_summary_until'] = 'until {date}';
$lang['ra_summary_count'] = '{count} times';

// Results.
$lang['ra_series_created'] = '{created} appointments created.';
$lang['ra_series_created_one'] = '1 appointment created.';
$lang['ra_series_skipped'] = '{skipped} dates with a conflict were skipped.';
$lang['ra_series_skipped_one'] = '1 date with a conflict was skipped.';
$lang['ra_series_updated'] = '{updated} appointments updated.';
$lang['ra_series_updated_one'] = '1 appointment updated.';
$lang['ra_series_deleted'] = '{deleted} appointments deleted.';
$lang['ra_series_deleted_one'] = '1 appointment deleted.';
$lang['ra_detached'] = 'The appointment is no longer part of the series.';

// Conflict dialog.
$lang['ra_conflicts_intro'] = 'Conflicts with the provider\'s schedule on {count} of the {total} dates:';
$lang['ra_no_conflicts'] = 'No conflicts. Dates that will be booked ({total}):';
$lang['ra_skip_conflicts'] = 'Skip conflicting dates';
$lang['ra_book_anyway'] = 'Book anyway';
$lang['ra_conflict_day_off'] = 'The provider does not work on this day.';
$lang['ra_conflict_outside_hours'] = 'Outside working hours ({start} - {end}).';
$lang['ra_conflict_break'] = 'Overlaps a break ({start} - {end}).';
$lang['ra_conflict_appointment'] = 'Overlaps another appointment ({start} - {end}).';
$lang['ra_conflict_unavailability'] = 'Overlaps an unavailability ({start} - {end}).';
$lang['ra_conflict_blocked_period'] = 'Falls in the blocked period "{name}".';
$lang['ra_conflict_same_series'] = 'Overlaps occurrence #{sequence} of this series.';

// Series panel.
$lang['ra_series_info'] = 'Occurrence {position} of {total} of a repeating appointment ({summary}).';
$lang['ra_series_hint'] = '"Save" changes only this occurrence.';
$lang['ra_series_marker'] = 'Repeating appointment';
$lang['ra_save_following'] = 'Save this and following';
$lang['ra_save_all'] = 'Save all';
$lang['ra_edit_series'] = 'Change series';
$lang['ra_edit_series_hint'] = 'Change the time or details below, then press "Save all" or "Save this and following".';
$lang['ra_detach'] = 'Detach from series';
$lang['ra_delete_single'] = 'Delete this one';
$lang['ra_delete_following'] = 'Delete this and following';
$lang['ra_delete_all'] = 'Delete entire series';
$lang['ra_confirm_delete'] = '{scope}? This cannot be undone.';
$lang['ra_title'] = 'Repeating appointment';

// Installation banner.
$lang['ra_not_installed'] = 'The recurring appointments extension is not set up yet: its database tables are missing.';
$lang['ra_install_now'] = 'Set up now';
$lang['ra_installed'] = 'Recurring appointments are ready to use.';

// Server side errors.
$lang['ra_error_generic'] = 'Something went wrong: {message}';
$lang['ra_error_permission'] = 'You do not have permission to do this. An administrator can check the permissions of your role.';
$lang['ra_error_session'] = 'Your session has expired. Reload the page and log in again.';
$lang['ra_error_server'] = 'The server gave an unexpected answer. Reload the page and try again. If it keeps happening, an administrator can check the log files in storage/logs.';
$lang['ra_error_invalid_request'] = 'The form was incomplete ({detail}). Reload the page and try again.';
$lang['ra_error_frequency'] = 'Unsupported repeat pattern.';
$lang['ra_error_interval'] = 'The repeat interval must be between 1 and {max}.';
$lang['ra_error_weekday'] = 'Pick a valid weekday.';
$lang['ra_error_nth'] = 'Pick a valid week of the month.';
$lang['ra_error_day'] = 'The day of the month must be between 1 and 31.';
$lang['ra_error_end_condition'] = 'Choose an end date or a number of occurrences.';
$lang['ra_error_until_before_start'] = 'The end date cannot be before the start date.';
$lang['ra_error_count'] = 'The number of occurrences must be between 1 and {max}.';
$lang['ra_error_too_many'] = 'This would create more than {max} occurrences, choose an earlier end date.';
$lang['ra_error_invalid_date'] = 'Invalid date: {value}.';
$lang['ra_error_number'] = 'Expected a whole number: {value}.';
$lang['ra_error_no_occurrences'] = 'The repeat pattern gives no dates before the end. Check the chosen weekdays and the end date.';
$lang['ra_error_all_conflict'] = 'Every date conflicts with the provider\'s schedule, so nothing was created. Choose another time, or use "Book anyway".';
$lang['ra_error_not_series'] = 'This appointment is no longer part of a repeating series. It may have been detached or deleted; reload the calendar.';
$lang['ra_error_not_installed'] = 'The recurring appointments extension is not set up yet. An administrator can click "Set up now" on the calendar page.';
$lang['ra_error_no_customer'] = 'No customer was selected.';
$lang['ra_error_existing'] = 'A new series cannot be created from an existing appointment.';
$lang['ra_error_staff_disabled'] = 'Creating repeating appointments is switched off. An administrator can switch it on in Settings → Booking.';
$lang['ra_error_duration'] = 'The appointment must last at least {min} minutes.';

// Settings → Booking.
$lang['ra_settings_title'] = 'Repeating appointments';
$lang['ra_settings_staff_enabled'] = 'Staff can create repeating appointments in the calendar';
$lang['ra_settings_staff_default_on'] = 'Switch "Repeat this appointment" on by default for new appointments';
$lang['ra_settings_max_occurrences'] = 'Most appointments in one series';
$lang['ra_settings_max_occurrences_hint'] = 'Protects against a typo in the end date. 400 is enough for a daily series over a year; at most 1000.';
$lang['ra_settings_booking_enabled'] = 'Customers can book a repeating appointment on the booking page';
$lang['ra_settings_booking_hint'] = 'Customers choose every week, every 2 weeks, every 4 weeks or every month. Every date is checked against the normal availability. Dates that are not free are skipped and shown to the customer after booking.';
$lang['ra_settings_booking_max'] = 'Most appointments per booking (the first one included)';
$lang['ra_settings_booking_notify'] = 'Confirmation e-mails';
$lang['ra_settings_booking_notify_all'] = 'One per appointment (each with its own cancel and reschedule link)';
$lang['ra_settings_booking_notify_first'] = 'Only for the first appointment';
$lang['ra_settings_save_hint'] = 'Saved with the Save button at the top of this page.';

// Public booking page and confirmation page.
$lang['ra_booking_repeat'] = 'Repeat';
$lang['ra_booking_no_repeat'] = 'Does not repeat';
$lang['ra_booking_daily'] = 'Every day';
$lang['ra_booking_weekly'] = 'Every week';
$lang['ra_booking_every_2_weeks'] = 'Every 2 weeks';
$lang['ra_booking_every_4_weeks'] = 'Every 4 weeks';
$lang['ra_booking_monthly'] = 'Every month (same weekday)';
$lang['ra_booking_yearly'] = 'Every year (same date)';
$lang['ra_booking_count'] = 'Number of appointments (including this one)';
$lang['ra_booking_weekdays'] = 'On these days';
$lang['ra_booking_until'] = 'Until (date)';
$lang['ra_booking_hint_max'] = 'At most {max} appointments. Dates that are not available are skipped. After booking you see which dates were booked.';
$lang['ra_booking_hint'] = 'Dates that are not available are skipped. After booking you see which dates were booked.';
$lang['ra_booking_summary'] = '{pattern}, {count} appointments in total';
$lang['ra_booking_summary_until'] = '{pattern}, until {date}';
$lang['ra_booking_booked_title'] = 'Your repeating appointment: {count} appointments booked';
$lang['ra_booking_skipped_title'] = 'Not available, so not booked:';
$lang['ra_booking_only_first'] = 'The following dates were not available, so only this appointment was booked.';
$lang['ra_booking_capped'] = 'Only the first {max} appointments were booked: that is the most per booking.';
