# Test checklist

## Automated

```bash
php tests/run.php     # recurrence rules, conflicts, translations, installer (no database needed)
```

The end-to-end test against a real Easy!Appointments in Docker covers most of the list below automatically, see
[tests/e2e/README.md](../tests/e2e/README.md). This manual list is for the UI and for checking a new Easy!Appointments
version by hand.

## Manual (on a test copy of the installation)

Preparation: one provider with a working plan of Mon–Fri 09:00–17:00 and a 12:00–13:00 break, a service of
60 minutes, and a customer with an e-mail address you can read. Log in as admin.

### Install

- [ ] `php install.php <path>` finishes, and `php index.php recurring_appointments status` says "installed".
- [ ] Running the installer again does not add a second hook line to `application/config/hooks.php`.
- [ ] `php install.php <path> --no-db`, then as admin: the calendar shows the **Set up now** banner, and the button
      creates the tables. Non-admin users see no banner and an unchanged calendar.
- [ ] Booking page, login and the other backend pages work as before. Only the calendar page source contains
      `recurring_appointments.js`.

### Create

- [ ] *New appointment* shows **Repeat this appointment**. Switching it on preselects the weekday of the start date and
      "After 10 occurrences".
- [ ] The weekday buttons start at the configured first weekday (Settings → General).
- [ ] Every Wednesday, 4 times → *Preview dates* lists 4 Wednesdays → *Save* → 4 appointments on the calendar.
- [ ] Every 2 weeks until a date 2 months ahead → every other Wednesday, the last one on or before the end date.
- [ ] Monthly, first Monday, 3 times → the first Monday of 3 consecutive months.
- [ ] Monthly on day 31, 4 times → months without a 31st are skipped.
- [ ] 261 occurrences, or an end date that would give more than 260 → clear error, nothing created.
- [ ] *Notify: Once* → exactly one e-mail per recipient. *No* → none. *For every occurrence* → one per date.
- [ ] A **new** customer filled in the window is created once.
- [ ] With the switch **off**, saving behaves exactly as before.

- [ ] Daily, 5 times → 5 days in a row; weekend days are reported as days off. Yearly → the same date every year.

### Conflicts

- [ ] A normal appointment on the 2nd Wednesday, then a weekly series over it → the dialog lists that date
      ("Overlaps another appointment").
- [ ] *Cancel* → nothing created. *Skip conflicting dates* → the others are created. *Book anyway* → all created.
- [ ] A day off, a break, a working plan exception (day off) and a blocked period each show their own reason.

### Edit and delete

- [ ] Series appointments show ↻ on the calendar in day, week and month view; normal appointments do not. Clicking
      one shows the series line and **Delete this and following** / **Delete entire series** in the pop-up (only
      for roles that may delete). After detaching or deleting, the marker updates on the next reload.
- [ ] Opening an occurrence shows "Occurrence n of N (summary)". A normal appointment shows no panel.
- [ ] Normal **Save** moves only that occurrence. **Save this and following** changes it and the later ones. **Save
      all** changes every occurrence. With only the time changed, each one keeps its date.
- [ ] Move the opened occurrence one day later and **Save all** → every occurrence moves one day, and the panel says
      the new weekday. With **Save this and following**, the earlier ones stay, and the later ones form a new series.
- [ ] **Detach from series** → the panel disappears, and later series edits leave that appointment alone.
- [ ] **Delete** → this one / this and following / entire series do what they say. Deleting through the calendar
      pop-up removes only that occurrence.
- [ ] A role without the delete permission sees no Delete menu; a role without edit permission sees no save buttons.

### Languages

- [ ] Switch your user language (top right → account) to Dutch, German, French or Spanish: the Repeat section, dialogs,
      conflict reasons and error messages are translated.
- [ ] Switch to a language without extension translations (e.g. Italian): the extension's text is English, while
      weekday names and Save/Cancel/Delete are in Italian.
- [ ] Date format DMY/MDY/YMD and 12/24-hour time (Settings → General) are used in the date list and the end date.

### Settings (Settings → Booking → Repeating appointments)

- [ ] The section is at the bottom, with staff on, default-on off, customers off, maximum 8, e-mails per appointment.
      Changing a value and pressing the page's **Save** keeps it after a reload.
- [ ] Staff off → the calendar has no Repeat switch; existing series can still be changed and deleted.
- [ ] Default on → a new appointment opens with Repeat switched on; an existing one shows no Repeat section.

### Customer bookings (customers switched on)

- [ ] The booking page shows **Repeat** under the available times; choosing a pattern shows the number field (2 up to
      the maximum). The confirmation step shows "↻ Every week, 4 appointments in total".
- [ ] Book every week 4 times with one of the dates already taken → the confirmation page lists 3 booked dates and the
      taken one under "Not available", in the customer's timezone. The calendar shows 3 appointments with ↻.
- [ ] With *one per appointment*, the customer gets an e-mail per appointment; with *only the first*, one e-mail.
- [ ] Every week with Monday added and an end date → Monday and the picked weekday until that date. The picked
      weekday cannot be switched off.
- [ ] Rescheduling an existing appointment through its e-mail link shows no Repeat choice.
- [ ] Customers off → the booking page is exactly as before.

### Permissions and sync

- [ ] **Provider** login: series only for yourself. A forged request for another provider returns 403.
- [ ] **Secretary** login: only for the assigned providers.
- [ ] With Google Calendar or CalDAV sync: every occurrence appears in the external calendar, and deleting the series
      removes them there.
- [ ] Webhooks fire `appointment_save` per created occurrence and `appointment_delete` per deleted one.

### Uninstall

- [ ] `php install.php <path> --uninstall --keep-db` → the calendar works without the Repeat switch, and appointments
      are still there.
- [ ] `php install.php <path> --uninstall` → the two tables are gone, and appointments are still there.
- [ ] `php index.php console install` (or an Easy!Appointments upgrade) is never blocked by the plugin's tables.
