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

The installer copies the files from `src/` into the installation, adds one hook line to
`application/config/hooks.php`, and creates two database tables. It is safe to run again, e.g. after an
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

**Uninstall but keep the data** for a later reinstall: `php install.php /var/www/html --uninstall --keep-db`.

**After an Easy!Appointments update:** the update may replace `application/config/hooks.php`. The plugin is then
switched off (the calendar works as before, without the Repeat switch) until you run the installer again. Your series
data is not touched.

## Repeat patterns

| Pattern | Example |
|---|---|
| Weekly, one or more weekdays | every Wednesday; every Monday and Thursday |
| Every N weeks | every 2 weeks on Wednesday |
| Monthly on the Nth weekday | first Monday, third Thursday, last Friday (every N months) |
| Monthly on a day number | the 15th of every month (months without that day are skipped) |
| End condition | an end date (inclusive) or a number of occurrences, at most 260 per series |

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
| `booking_enabled` | `0` |
| `booking_max` | `8` (2–52) |
| `booking_notify` | `all` (or `first`) |

## How customer bookings stay safe

- The core books the first appointment exactly as always (captcha, customer record, availability, e-mail).
- Every following date must be a time slot the booking page itself would offer: the core availability library is used
  (working plan, breaks, exceptions, other appointments, buffers, group size, "book in advance") plus the future
  booking limit. Taken dates are skipped, never double-booked.
- Customers can only choose from four patterns and up to the configured maximum. A repeat can only be added in the
  same request that booked the first appointment; rescheduling never adds one.
- Each appointment is a normal appointment with its own cancel and reschedule link.

## How it integrates

Easy!Appointments has no plugin system, so this plugin uses only the extension points CodeIgniter itself provides:

| Part | How |
|---|---|
| Server | Its own controller (`recurring_appointments/...`), model, helper and libraries. CodeIgniter routes it automatically. |
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

- No daily or yearly patterns yet; they are straightforward to add in `Recurrence_rule`.
- The small calendar pop-up names the series but has no series actions (its delete button removes just that one
  occurrence). Series actions are in the appointment window.
- Customers choose from four fixed patterns; they cannot pick several weekdays or an end date.
- When a customer books with "Any provider", the following dates use the provider the first appointment got.
- The REST API (`/api/v1/appointments`) sees occurrences as plain appointments; there are no series endpoints.
- A series is capped at 260 occurrences. A longer end date is refused rather than truncated.
