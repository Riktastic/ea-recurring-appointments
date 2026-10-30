# Recurring appointments for Easy!Appointments

Repeating appointments for [Easy!Appointments](https://easyappointments.org): every Wednesday, every two weeks, the
first Monday of the month, and so on.

- Each appointment in a series is a normal appointment, so e-mails and calendar sync keep working.
- Dates that clash with the provider's schedule are shown first, never silently double-booked.
- Change or delete one appointment, the following ones, or the whole series.
- Optional: customers can book a repeating appointment on the booking page.
- In English, Dutch, German, French and Spanish (other languages fall back to English).

Works with Easy!Appointments 1.6 and PHP 8.2 or newer.

## Install

1. Download the zip from the [releases](../../releases) page and unzip it (not inside your Easy!Appointments folder).
2. Run:
   ```bash
   php install.php /path/to/easyappointments
   ```
3. Open the calendar and create an appointment: **Repeat this appointment** is there.

Settings are at Settings → Booking → **Repeating appointments**.

To remove it: `php install.php /path/to/easyappointments --uninstall`. Your appointments stay.

## Documentation

- [User guide](docs/USER_GUIDE.md) · [Handleiding (Nederlands)](docs/HANDLEIDING.md)
- [Technical details](docs/TECHNICAL.md): other ways to install, how it works, adding a language, development
- [Changelog](CHANGELOG.md)

## License

GPL-3.0, the same as Easy!Appointments. An independent community extension, not affiliated with the
Easy!Appointments project.
