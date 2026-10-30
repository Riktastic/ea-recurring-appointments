# Technical details

For administrators who install the plugin in a special setup, and for developers.

## Compatibility

| | |
|---|---|
| Easy!Appointments 1.6.x | built for and tested against 1.6.1 (end-to-end with MySQL 8.4, PHP 8.3) |
| Easy!Appointments 1.5.x | written to degrade gracefully (optional features are detected), not yet tested |
| PHP | 8.2, 8.3, 8.4 |
| Database | MySQL / MariaDB (InnoDB) |

## Other ways to install

The installer copies the files from `src/` into the installation, adds one guarded line to
`application/config/hooks.php` (the pages) and one to `application/config/routes.php` (the REST API), and creates two
database tables. It is safe to run again, e.g. after an
Easy!Appointments update or to upgrade the plugin. `--dry-run` shows what it would do.

**Docker:** copy or mount the folder into the container and run the installer there, for example
`docker exec -it <container> php /tmp/recurring/install.php /var/www/html`.

**Database not reachable from the shell?** Run `php install.php /var/www/html --no-db`, then log in as admin and click
**Set up now** in the banner on the calendar page. Running `sql/install.sql` by hand also works.

**No shell at all?** Copy the contents of `src/` over your installation (they only add new files), add this line to the
end of `application/config/hooks.php`, then use the **Set up now** banner:

```php
if (is_file(APPPATH . 'config/recurring_appointments_hooks.php')) { include APPPATH . 'config/recurring_appointments_hooks.php'; }
```

For the REST API, also add this line to the end of `application/config/routes.php`:

```php
if (is_file(APPPATH . 'config/recurring_appointments_routes.php')) { include APPPATH . 'config/recurring_appointments_routes.php'; }
```

**Uninstall but keep the data** for a later reinstall: `php install.php /var/www/html --uninstall --keep-db`.

**After an Easy!Appointments update:** the update may replace `application/config/hooks.php` and `routes.php`. The plugin is then
switched off (the calendar works as before, without the Repeat switch) until you run the installer again. Your series
data is not touched.

## Repeat patterns

| Pattern | Example |
|---|---|
| Daily | every day; every 3rd day |
| Weekly, one or more weekdays | every Wednesday; every Monday and Thursday |
| Every N weeks | every 2 weeks on Wednesday |
| Monthly on the Nth weekday | first Monday, third Thursday, last Friday (every N months) |
| Monthly on a day number | the 15th of every month (months without that day are skipped) |
| Yearly | every 14 March (29 February: leap years only), every N years |
| End condition | an end date (inclusive) or a number of occurrences |

A series has at most 400 occurrences by default (enough for a daily series over a year). Administrators can change
this on Settings → Booking, up to 1000. A rule that would create more is refused with a clear message, so a typo in
the end date never creates thousands of appointments. Customers get their own, lower maximum per booking; there, an
end date that asks for more is capped and the confirmation page says so.

The pattern starts from the start date's weekday, week of the month and day number. If the start date does not match
the pattern itself, the series starts at the first date that does.

"Save this and following" / "Save all" apply time of day, duration, service, provider, customer, location, meeting
link, colour, status and notes. Moving the opened occurrence to another day moves the others by the same number of
days; with "this and following" the moved occurrences become a series of their own.

## Settings

Stored as rows in the core settings table, named `recurring_appointments_*`, loaded and saved by the core
Settings → Booking page, and removed on uninstall.

| Setting | Default |
|---|---|
| `staff_enabled` | `1` |
| `staff_default_on` | `0` |
| `max_occurrences` | `400` (2–1000) |
| `booking_enabled` | `0` |
| `booking_max` | `8` (2–52) |
| `booking_notify` | `all` (or `first`) |

## How customer bookings stay safe

- The core books the first appointment exactly as always (captcha, customer record, availability, e-mail).
- Every following date must be a time slot the booking page itself would offer: the core availability library is used
  (working plan, breaks, exceptions, other appointments, buffers, group size, "book in advance") plus the future
  booking limit. Taken dates are skipped, never double-booked.
- Customers choose from six patterns (every day, week, 2 weeks, 4 weeks, month, year), for the weekly ones optionally several
  weekdays, and an end: a number of appointments or an end date. Never more than the configured maximum. A repeat
  can only be added in the same request that booked the first appointment; rescheduling never adds one.
- With "Any provider", a date on which the first appointment's provider is not free goes to another provider of the
  service who is; the confirmation page then names the provider per date.
- Each appointment is a normal appointment with its own cancel and reschedule link.

## How it integrates

Easy!Appointments has no plugin system, so this plugin uses only the extension points CodeIgniter itself provides:

| Part | How |
|---|---|
| Server | Its own controller (`recurring_appointments/...`), API controller, model, helper and libraries. The series work itself is in `Recurring_series`, shared by the calendar endpoints and the REST API. |
| REST API routes | One guarded include line at the end of `application/config/routes.php` (added and removed by the installer), which adds the `api/v1/appointment_series` routes. |
| Data | Two tables of its own, `ea_appointment_series` and `ea_appointment_series_occurrences`. The core tables are not altered. |
| Schema | Created by the installer, `php index.php recurring_appointments install`, or the admin **Set up now** button. Not a numbered core migration: those numbers belong to Easy!Appointments, a future upstream migration would collide, and core blocks every page while a migration is pending. |
| No foreign keys into core tables | They would block core migrations that change those columns (verified: they break `console install`). A link stores the appointment ID **and** its unique hash, so a deleted or re-used ID never matches. Stale links are cleaned up on the next use. |
| UI | A `display_override` hook adds a script to the backend calendar, to Settings → Booking and (when switched on) to the public booking page, and the result list to the booking confirmation page. Every other page is sent unchanged, and if anything in the hook fails, the original page is sent unchanged. |
| Customer bookings | The same hook sees the JSON answer of the core `booking/register`. When that booked an appointment and the request carried a repeat choice, `Recurring_booking` books the following free dates. The core booking code is not changed. |
| Translations | `application/language/<language>/recurring_appointments_lang.php`, next to the core files. Missing keys or languages fall back to English; weekday names and common buttons come from the core translations. |

### Endpoints

All take POST with the CSRF token and need a backend session.

| Endpoint | Purpose |
|---|---|
| `recurring_appointments/preview` | expand a rule and list conflicts, writes nothing |
| `recurring_appointments/create` | create a series (`conflict_mode`: abort/skip/force, `notify`: none/first/all) |
| `recurring_appointments/info` | series details of an appointment |
| `recurring_appointments/update` | `scope`: following/all |
| `recurring_appointments/delete` | `scope`: single/following/all |
| `recurring_appointments/detach` | turn one occurrence into a regular appointment |
| `recurring_appointments/markers` | which of the given appointment IDs belong to a series (for the ↻ marker) |
| `recurring_appointments/install` | create the tables (admins) |
| CLI `install` / `uninstall` / `status` | `php index.php recurring_appointments <command>` |

### REST API

Same authentication as the core API (API token as `Authorization: Bearer …`, or admin Basic auth) and the same field
names as `/api/v1/appointments`. The occurrences are normal appointments: read or change one through
`/api/v1/appointments/{id}`.

| Request | What it does |
|---|---|
| `GET /api/v1/appointment_series` | list series; filters `providerId`, `customerId`, `serviceId`; `limit`, `page` |
| `GET /api/v1/appointment_series/{id}` | one series with its occurrences |
| `POST /api/v1/appointment_series` | create a series (below); `201`, or `409` with the conflicting dates |
| `PUT /api/v1/appointment_series/{id}` | change every occurrence, or from a date on with `"from": "2027-02-01"`; `200`, or `409` |
| `DELETE /api/v1/appointment_series/{id}` | delete every occurrence, or from a date on with `?from=2027-01-01`; `?notify=none\|first\|all` |

```json
POST /api/v1/appointment_series
{
  "start": "2027-01-06 10:00:00",
  "end": "2027-01-06 11:00:00",
  "serviceId": 1,
  "providerId": 2,
  "customerId": 4,
  "notes": "Weekly check-up",
  "rule": {"frequency": "weekly", "interval": 1, "weekdays": [3], "count": 10},
  "conflictMode": "abort",
  "notify": "none"
}
```

`rule.frequency` is `daily`, `weekly` (`weekdays`: 1 = Monday … 7 = Sunday), `monthly_weekday` (`nth`: 1–5 or -1 for
last, `weekday`), `monthly_day` (`day`) or `yearly` (`month`, `day`), each with `interval` and either `count` or
`until` (`Y-m-d`). Missing values default to the start date. `conflictMode`: `abort` (default, nothing is created),
`skip` or `force`.

```json
PUT /api/v1/appointment_series/12
{"start": "2027-01-13 14:00:00", "notes": "Moved to the afternoon", "from": "2027-01-13", "notify": "all"}
```

`PUT` takes the same appointment fields. `start`/`end` describe the first occurrence in scope: the others get the same
time of day and duration (a `start` without `end` keeps the duration). Moving that occurrence to another day moves
every occurrence in scope by the same number of days; with `from`, the moved ones become a series of their own and the
answer is that new series. Fields you leave out stay as they are.

## Security

| Risk | How it is handled |
|---|---|
| SQL injection | Every query goes through the CodeIgniter query builder, which escapes values. The only raw SQL (orphan cleanup, table setup) contains table names from the configured prefix, never request data. IDs are cast to integers and dates are checked against `Y-m-d` before use. |
| XSS | The browser scripts build HTML only from escaped values (`&`, `<`, `>`, `"` and `'`), or use `.text()`. Page configuration is embedded with `JSON_HEX_TAG`/`QUOT`/`APOS`/`AMP`, so a `</script>` in a setting or translation cannot break out. The confirmation page uses `html_escape()`. Server responses are JSON. |
| CSRF | All calendar endpoints are POST and covered by the core CSRF protection. The REST API uses token or admin authentication like the core API. |
| Access | Every endpoint checks the role permission (view, add, edit, delete appointments) and, like the core calendar, that providers only touch their own and secretaries their providers' appointments; this is checked for every occurrence in scope. Series details and calendar markers only include appointments the user may see. |
| Public booking | The repeat choice is only accepted in the same request that booked the first appointment, from a fixed list of patterns, within the configured maximum, and only on time slots the booking page itself offers. |

The end-to-end test includes SQL injection attempts on IDs, dates, filters and the booking request, and script
payloads in settings and in a blocked period name.

## Adding a language

Copy `src/application/language/english/recurring_appointments_lang.php` to the folder name Easy!Appointments uses
(for example `italian`), translate the values, and keep placeholders such as `{count}` unchanged. `php tests/run.php`
checks that every language file has the same keys and placeholders as the English one. Pull requests are welcome.

## Development

```bash
php tests/run.php   # recurrence rules, conflicts, translations and the installer; no database needed
```

The end-to-end test against a real Easy!Appointments in Docker is described in
[tests/e2e/README.md](../tests/e2e/README.md), and a manual checklist is in [TESTING.md](TESTING.md).

Releases: bump `Recurring_appointments::VERSION`, add a `CHANGELOG.md` entry, and push a tag `vX.Y.Z`. The release
workflow then tests, builds the zip and publishes it.

## Known limitations

- The calendar pop-up's **Change series** opens the appointment window with the series panel, since a change needs the
  full form (time, provider, customer, ...); it is not a separate quick-edit form.
- Changing the pattern itself (e.g. weekly → monthly) is done by deleting the following occurrences and creating a new
  series; a change keeps the pattern and moves or edits the existing occurrences.
- With "Any provider", the extension keeps the first appointment's provider whenever that provider is free, and only
  then picks another one; it does not spread a series over providers.
