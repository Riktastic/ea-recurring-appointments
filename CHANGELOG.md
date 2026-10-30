# Changelog

All notable changes to this plugin are documented here. The format follows [Keep a Changelog](https://keepachangelog.com),
and versions follow [Semantic Versioning](https://semver.org).

## [1.0.0] - 2026-10-30

### Added

- Repeating appointments from the backend calendar: weekly on one or more weekdays, every N weeks, monthly on the Nth
  (or last) weekday, monthly on a day number; ending on a date or after a number of occurrences.
- Conflict detection against other appointments, unavailable times, the working plan, breaks, working plan exceptions
  and blocked periods, with skip / book anyway / cancel.
- Series panel for existing occurrences: save this and following, save all, detach, and delete one / following / all.
  Moving an occurrence to another day moves the others along; "this and following" then splits off a new series.
- A ↻ marker on series appointments in the calendar, and the series line in the appointment pop-up.
- Customers can book a repeating appointment on the public booking page (off by default): every week, every 2 or 4
  weeks or every month, only on time slots the booking page itself offers. The confirmation page lists the booked and
  skipped dates in the customer's timezone.
- Settings on Settings → Booking: staff on/off, Repeat on by default, customer booking on/off, maximum per booking,
  confirmation e-mails per appointment or only for the first.
- Plain-language user guide in English and Dutch, troubleshooting table, and clear translated messages for missing
  permissions, an expired session and unexpected server answers.
- Notification choice per series: once, for every occurrence, or none.
- Translations: English, Dutch, German, French and Spanish, with an English fallback for other languages. Weekday
  names and common words come from the Easy!Appointments translations.
- Cross-platform installer (`install.php`) with uninstall and dry run, plus a "Set up now" button for admins.
- Unit, translation and installer tests, and an end-to-end test against a real Easy!Appointments in Docker.
