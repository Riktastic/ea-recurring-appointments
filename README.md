# Recurring appointments for Easy!Appointments

A drop-in plugin for [Easy!Appointments](https://easyappointments.org) that lets staff create and manage repeating
appointments from the backend calendar: **every Wednesday**, **every two weeks**, **the first Monday of the month**,
and so on, until an end date or for a number of times.

- Every occurrence is a **normal appointment**, so the calendar, e-mail notifications, webhooks and Google/CalDAV sync
  keep working as they do now.
- **Conflicts are reported, never silently double-booked.** Each date is checked against the provider's other
  appointments, unavailable times, working plan, breaks, day-off exceptions and blocked periods. You then choose to
  skip those dates, book anyway, or cancel.
- **Edit or delete** one occurrence, this one and the following ones, or the whole series.
- **Customers can book repeating appointments too** (optional): every week, every 2 or 4 weeks, or every month, on the
  public booking page. Only dates that the booking page itself would offer are booked; the others are skipped and
  shown to the customer.
- **Settings** on Settings → Booking: switch the feature on or off for staff and for customers, have the Repeat switch
  on by default, limit how many appointments a customer can book at once.
- **Multilingual:** follows the user's Easy!Appointments language. English, Dutch, German, French and Spanish are
  included; any other language falls back to English for the extension's own text, while weekday names and common
  buttons come from Easy!Appointments' own translations. Dates and times follow your configured date format, time
  format and first weekday.
- **No core files edited** apart from one guarded line in `application/config/hooks.php`, which the installer adds and
  removes for you.

| Compatibility | |
|---|---|
| Easy!Appointments 1.6.x | built for and tested against 1.6.1 (end-to-end with MySQL 8.4, PHP 8.3) |
| Easy!Appointments 1.5.x | written to degrade gracefully (optional features are detected), not yet tested |
| PHP | 8.2, 8.3, 8.4 |
| Database | MySQL / MariaDB (InnoDB) |

## Repeat patterns

| Pattern | Example |
|---|---|
| Weekly, one or more weekdays | every Wednesday; every Monday and Thursday |
| Every N weeks | every 2 weeks on Wednesday |
| Monthly on the Nth weekday | first Monday, third Thursday, last Friday (every N months) |
| Monthly on a day number | the 15th of every month (months without that day are skipped) |
| End condition | an end date (inclusive) or a number of occurrences, at most 260 per series |

## Installation

1. Download the latest `easyappointments-recurring-appointments-vX.Y.Z.zip` from the
   [releases](../../releases) page and unzip it anywhere (not inside your Easy!Appointments folder).
2. Run the installer with the path of your Easy!Appointments installation:
   ```bash
   php install.php /var/www/html
   ```
   It copies the files from `src/` into the installation, adds the hook line, and creates the two database tables.
   It is safe to run again, e.g. after an Easy!Appointments update or to upgrade the plugin. Use `--dry-run` to see
   what it would do.
3. Open the calendar and create an appointment: the **Repeat this appointment** switch is there.
4. Optional: Settings → Booking → **Repeating appointments** to let customers book repeating appointments too.

**Docker:** copy or mount the folder into the container and run the installer there, for example
`docker exec -it <container> php /tmp/recurring/install.php /var/www/html`.

**Database not reachable from the shell?** Run `php install.php /var/www/html --no-db`, then log in as admin and click
**Set up now** in the banner on the calendar page. Running `sql/install.sql` by hand also works.

**No shell at all?** Copy the contents of `src/` over your installation (they only add new files), add this line to the
end of `application/config/hooks.php`, then use the **Set up now** banner:

```php
if (is_file(APPPATH . 'config/recurring_appointments_hooks.php')) { include APPPATH . 'config/recurring_appointments_hooks.php'; }
```

### Uninstall

```bash
php install.php /var/www/html --uninstall            # removes the files, the hook line and the two tables
php install.php /var/www/html --uninstall --keep-db  # keeps the tables, e.g. for a later reinstall
```

Created appointments always stay: they become regular single appointments.

### After an Easy!Appointments update

An update may replace `application/config/hooks.php`. The plugin is then switched off (the calendar works as before,
without the Repeat switch) until you run `php install.php /var/www/html` again. Your series data is not touched.

## Settings

Settings → Booking has a **Repeating appointments** section at the bottom. It is saved with the normal **Save** button
at the top of that page.

| Setting | Default | What it does |
|---|---|---|
| Staff can create repeating appointments in the calendar | on | Shows the Repeat section in the appointment window. When off, existing series can still be changed and deleted. |
| Switch "Repeat this appointment" on by default | off | New appointments start with Repeat switched on (staff can still switch it off). |
| Customers can book a repeating appointment on the booking page | off | Adds the Repeat choice to the public booking page. |
| Most appointments per booking | 8 | The most a customer can book at once, the first one included (2–52). |
| Confirmation e-mails | one per appointment | One e-mail per appointment, each with its own cancel and reschedule link, or only the usual one for the first. |

## Usage

A step-by-step guide for staff and administrators, without technical details, is in
[docs/USER_GUIDE.md](docs/USER_GUIDE.md) (Dutch: [docs/HANDLEIDING.md](docs/HANDLEIDING.md)).

**Create:** Calendar → **+** → **Appointment**. Fill in the appointment, switch on **Repeat this appointment**, choose
the pattern and the end, and optionally use **Preview dates**. Then press **Save**. If dates conflict, a dialog lists
them with the reason and offers **Skip conflicting dates**, **Book anyway** or **Cancel**.

The pattern starts from the start date's weekday, week of the month and day number. If the start date does not match
the pattern itself, the series starts at the first date that does.

**Notifications:** *Once (first occurrence)* (default), *No*, or *For every occurrence*. A weekly series over a year
would otherwise send 52 e-mails.

**Recognize:** appointments that belong to a series show a ↻ icon on the calendar (day, week and month view), and
their pop-up says "Occurrence 3 of 12 of a repeating appointment (every week on Wednesday)".

**Change:** open an occurrence. A panel shows "Occurrence 3 of 12 …":

- **Save** (the normal button) changes only this occurrence.
- **Save this and following** / **Save all** applies time of day, duration, service, provider, customer, location,
  meeting link, colour, status and notes. If you move the opened occurrence to another day, the others move by the
  same number of days (every Wednesday → every Thursday). With *this and following*, the moved occurrences become a
  series of their own, and the earlier ones keep their day. Conflicts are reported in the same way.
- **Detach from series** makes it a regular appointment.
- **Delete** → this one / this and following / entire series.

Deleting an occurrence with the normal calendar delete button is fine too.

The buttons follow the user's role permissions (add, edit and delete appointments). Providers and secretaries can
only manage series of the providers they are allowed to manage, exactly like single appointments.

**Customers** (when switched on in the settings): on the booking page, under the available times, the customer
chooses **Repeat** (does not repeat / every week / every 2 weeks / every 4 weeks / every month on the same weekday)
and the number of appointments. The confirmation step shows the choice. After booking, the confirmation page lists
the booked dates and the dates that were not available, in the customer's own timezone.

How it stays safe:

- The core books the first appointment exactly as always (captcha, customer record, availability, e-mail).
- Every following date must be a time slot the booking page itself would offer: the same availability check is used
  (working plan, breaks, exceptions, other appointments, buffers, group size, "book in advance" and the future booking
  limit). Taken dates are skipped, never double-booked.
- Customers can only choose from the four patterns and up to the configured maximum. A repeat can only be added in the
  same request that booked the first appointment; rescheduling an existing appointment never adds one.
- Each appointment is a normal appointment with its own cancel and reschedule link.

## Troubleshooting

| What you see | What to do |
|---|---|
| No **Repeat this appointment** switch in the appointment window | Check Settings → Booking → *Staff can create repeating appointments*. After an Easy!Appointments update, run the installer again (the update may have replaced `application/config/hooks.php`). The switch is not shown when you open an existing appointment, only for new ones. |
| Yellow banner "not set up yet" on the calendar | An administrator clicks **Set up now**, or runs `php install.php /path/to/easyappointments` again. |
| Customers do not see the Repeat choice | Check Settings → Booking → *Customers can book a repeating appointment*, and save. It is never shown when a customer reschedules an existing appointment. |
| "Your session has expired" | Log in again; nothing was saved. |
| "You do not have permission to do this" | The user's role lacks the add, edit or delete permission for appointments (Settings → Users → Roles), or the appointment belongs to a provider the user may not manage. |
| "The server gave an unexpected answer" | Reload and try again. If it keeps happening, look in `storage/logs/` of the Easy!Appointments installation; the extension's lines start with "Recurring appointments". |
| Fewer appointments than asked for | Those dates conflicted (staff: you chose *Skip conflicting dates*; customers: the time was not available). The dialog or the confirmation page lists them. |

## How it integrates

Easy!Appointments has no plugin system, so this plugin uses only the extension points CodeIgniter itself provides:

| Part | How |
|---|---|
| Server | Its own controller (`recurring_appointments/...`), model, helper and libraries. CodeIgniter routes it automatically. |
| Data | Two tables of its own, `ea_appointment_series` and `ea_appointment_series_occurrences`. The core tables are not altered. |
| Schema | Created by the installer, `php index.php recurring_appointments install`, or the admin **Set up now** button. It does not use a numbered core migration, because those numbers belong to Easy!Appointments: a future upstream migration would collide, and core blocks every page while a migration is pending. |
| No foreign keys into core tables | Foreign keys would block core migrations that change those columns (verified: they break `console install`). A link stores the appointment ID **and** its unique hash, so a deleted or re-used ID never matches. Stale links are cleaned up on the next use. |
| UI | A CodeIgniter `display_override` hook adds a script to the backend calendar, to Settings → Booking and (when switched on) to the public booking page, and the result list to the booking confirmation page. Every other page is sent unchanged, and if anything in the hook fails, the original page is sent unchanged. |
| Customer bookings | The same hook sees the JSON answer of the core `booking/register`. When that booked an appointment and the request carried a repeat choice, `Recurring_booking` books the following free dates. The core booking code is not changed. |
| Settings | Rows in the core settings table, named `recurring_appointments_*`. Loaded and saved by the core Settings → Booking page; removed on uninstall. |
| Translations | `application/language/<language>/recurring_appointments_lang.php`, next to the core files. Missing keys or languages fall back to English. |

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
[tests/e2e/README.md](tests/e2e/README.md), and a manual checklist is in [docs/TESTING.md](docs/TESTING.md).

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

## License

GPL-3.0, the same as Easy!Appointments. See [LICENSE](LICENSE). This is an independent community extension and is not
affiliated with the Easy!Appointments project.
