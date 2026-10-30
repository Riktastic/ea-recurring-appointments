# User guide: repeating appointments

For staff and administrators. No technical knowledge needed. (Nederlands: [HANDLEIDING.md](HANDLEIDING.md))

A *repeating appointment* is a series of normal appointments, for example every Wednesday at 10:00 for ten weeks.
Every appointment in the series is a real appointment: it shows on the calendar, sends the usual e-mails and can be
changed on its own.

On the calendar, appointments that belong to a series have a **↻** in front of their name.

## Make a repeating appointment

1. Calendar → **+** → **Appointment**.
2. Fill in the appointment as usual: service, provider, customer, date and time.
3. Switch on **Repeat this appointment**.
4. Choose how often:
   - **Weekly**: pick the weekday(s) and *every 1 week* (or every 2, 3 … weeks).
   - **Monthly on a weekday**: for example *the first Monday*.
   - **Monthly on a day number**: for example *the 15th*. Months without that day (like the 31st in April) are skipped.
5. Choose when it ends: **After** a number of times, or **On date**.
6. Optional: **Preview dates** shows every date and warns about problems.
7. Choose **Notify customer and provider**: *Once (first occurrence)* is usually best.
8. Press **Save**.

If some dates are not possible (the provider is off, has a break, or already has an appointment), a window lists
those dates with the reason. Choose:

- **Skip conflicting dates**: book only the dates that are free.
- **Book anyway**: book every date, also the ones with a problem (a double booking).
- **Cancel**: book nothing, so you can change the time or pattern first.

Nothing is ever double-booked without you choosing *Book anyway*.

## Change a repeating appointment

Click the appointment, then **Edit**. At the top of the window you see for example *"Occurrence 3 of 10 of a
repeating appointment (every week on Wednesday)"*.

Change what you need (time, duration, notes, provider …), then press:

| Button | Changes |
|---|---|
| **Save** (bottom of the window) | only this appointment |
| **Save this and following** | this appointment and all later ones in the series |
| **Save all** | every appointment in the series |

Moving to another day works too: move the opened appointment from Wednesday to Thursday and press **Save all**, and
the whole series moves to Thursday. With **Save this and following**, the earlier appointments stay on Wednesday.

**Detach from series** turns this one appointment into a normal appointment. Later changes to the series leave it alone.

## Delete

In the same window, open **Delete** and choose:

- **Delete this one**
- **Delete this and following**
- **Delete entire series**

You are always asked to confirm. The normal delete button in the small pop-up on the calendar deletes only that one
appointment.

## Customers booking a repeat (if your administrator switched it on)

On the booking page, under the times, customers see **Repeat** with *Does not repeat*, *Every week*, *Every 2 weeks*,
*Every 4 weeks* and *Every month (same weekday)*, and the number of appointments. Only times that are really free are
booked. After booking, the customer sees which dates were booked and which were not available.

These appointments show on your calendar with the **↻** and can be changed like any other series.

## For administrators: settings

Settings → Booking, section **Repeating appointments** at the bottom. Press **Save** at the top of the page afterwards.

- **Staff can create repeating appointments in the calendar**: on or off for staff.
- **Switch "Repeat this appointment" on by default**: new appointments start with Repeat on.
- **Customers can book a repeating appointment on the booking page**: shows the Repeat choice to customers.
- **Most appointments per booking**: the most a customer can book at once (the first one included).
- **Confirmation e-mails**: one e-mail per appointment (each with its own cancel link) or only for the first.

Who can create, change or delete a series follows the normal role permissions for appointments (Settings → Users →
Roles).

## Messages you might see

| Message | Meaning |
|---|---|
| *Conflicts with the provider's schedule on 2 of the 10 dates* | Two dates are not possible; choose skip, book anyway or cancel. |
| *Every date conflicts with the provider's schedule* | No date is possible at this time. Choose another time. |
| *The repeat pattern gives no dates before the end* | Check the chosen weekdays and the end date. |
| *This would create more than 260 occurrences* | Choose an earlier end date or fewer times. |
| *You do not have permission to do this* | Your role may not add, change or delete appointments. Ask an administrator. |
| *Your session has expired* | Reload the page and log in again. |
| *This appointment is no longer part of a repeating series* | Someone detached or deleted it. Reload the calendar. |
| *The server gave an unexpected answer* | Reload and try again. If it keeps happening, an administrator can look in `storage/logs/` of the Easy!Appointments installation (lines starting with "Recurring appointments"). |

## Something is missing?

| What you see | What to do |
|---|---|
| No **Repeat this appointment** switch | It only shows for new appointments, not when you open an existing one. Otherwise check Settings → Booking → *Staff can create repeating appointments*. After an Easy!Appointments update, the administrator runs the installer again. |
| Yellow banner "not set up yet" on the calendar | An administrator clicks **Set up now**. |
| Customers do not see the Repeat choice | Check Settings → Booking → *Customers can book a repeating appointment*, and save. It never shows when a customer reschedules an existing appointment. |
| Fewer appointments than asked for | Those dates were not free. The dialog (staff) or the confirmation page (customers) lists them. |
