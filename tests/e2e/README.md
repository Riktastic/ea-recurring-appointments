# End-to-end test

`http_test.php` drives a real Easy!Appointments over HTTP, the way the browser does (session cookie and CSRF token),
and checks the results in the database. Currently it runs 51 checks:

- With the extension installed, a core reinstall (`console install`, which runs every migration down and up again)
  still works, and the extension install is idempotent.
- The calendar page gets the script and the strings of the user's language. Other pages are left alone.
- Creating a series, every two weeks, first Monday of the month, conflicts (other appointments, day off, breaks,
  working hours), and the skip mode.
- "Save this and following", "delete this and following", detach, the core delete button, and stale links.
- "Save all" with a new time, moving a whole series to another day, splitting it with "this and following", the
  calendar markers, and deleting one occurrence or the entire series.
- Settings: the defaults, staff switched off, and customer bookings: off, every week with a taken date skipped, the
  confirmation list, an unknown pattern, the maximum, and rescheduling.
- Daily and yearly patterns, the configurable maximum per series, customers with several weekdays and an end date,
  an end date capped at the maximum, "any provider" moving a busy date to a second provider (created through the
  core REST API), customers booking every day (day off skipped) and every year (past the booking limit skipped), and
  the series REST API (create, list, show, change all, move the following ones, 409 on conflicts, delete following,
  delete, 401).
- Security: SQL injection attempts on IDs, dates, API filters and the booking request; script payloads in settings
  (page config) and in a blocked period name.
- Permissions: a provider may not book for another provider, and logged-out requests are refused.
- Translated UI and errors in Dutch and German, and the English fallback for a language without extension
  translations (Greek).

> **Only use a throwaway copy.** The test reinstalls the database, rewrites a provider's working plan and password, and
> changes user languages.

## Run it

You need Docker and a **copy** of an Easy!Appointments installation (a release download, or a copy of your own
installation including `vendor/`).

```bash
cp -r /path/to/easyappointments /tmp/ea-copy
cp tests/e2e/config.php /tmp/ea-copy/config.php

cd tests/e2e
EA_PATH=/tmp/ea-copy docker compose up -d --build

docker compose exec web sh -c "chown -R www-data:www-data /var/www/html/storage \
  && cd /var/www/html && php index.php console install \
  && php /plugin/install.php /var/www/html"

docker compose exec web php /plugin/tests/e2e/http_test.php \
  --url=http://localhost --db-host=db --db-user=ea --db-pass=ea-test --db-name=easyappointments \
  --ea-path=/var/www/html
```

Then open <http://localhost:8089> (login `administrator` / `administrator`) to try the UI by hand, following
[docs/TESTING.md](../../docs/TESTING.md).

Clean up with `docker compose down -v`.
