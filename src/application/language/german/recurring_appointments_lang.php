<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * Deutsch. Fehlende Schlüssel fallen auf Englisch zurück.
 * ---------------------------------------------------------------------------- */

$lang['ra_repeat'] = 'Wiederholen';
$lang['ra_repeat_this'] = 'Diesen Termin wiederholen';
$lang['ra_frequency_weekly'] = 'Wöchentlich';
$lang['ra_frequency_monthly_weekday'] = 'Monatlich an einem Wochentag (z. B. erster Montag)';
$lang['ra_frequency_monthly_day'] = 'Monatlich an einem Kalendertag (z. B. am 15.)';
$lang['ra_every'] = 'Alle';
$lang['ra_weeks'] = 'Woche(n)';
$lang['ra_months'] = 'Monat(e)';
$lang['ra_on'] = 'Am';
$lang['ra_the'] = 'Am';
$lang['ra_day'] = 'Tag';
$lang['ra_ends'] = 'Endet';
$lang['ra_after'] = 'Nach';
$lang['ra_occurrences'] = 'Terminen';
$lang['ra_on_date'] = 'Am Datum';
$lang['ra_nth_1'] = 'ersten';
$lang['ra_nth_2'] = 'zweiten';
$lang['ra_nth_3'] = 'dritten';
$lang['ra_nth_4'] = 'vierten';
$lang['ra_nth_5'] = 'fünften';
$lang['ra_nth_last'] = 'letzten';
$lang['ra_preview'] = 'Termine anzeigen';
$lang['ra_notify'] = 'Kunde und Dienstleister benachrichtigen';
$lang['ra_notify_none'] = 'Nein';
$lang['ra_notify_first'] = 'Einmal (erster Termin)';
$lang['ra_notify_all'] = 'Für jeden Termin';

$lang['ra_summary_weekly'] = 'alle {interval} Woche(n) am {days}';
$lang['ra_summary_monthly_weekday'] = 'alle {interval} Monat(e) am {nth} {weekday}';
$lang['ra_summary_monthly_day'] = 'alle {interval} Monat(e) am {day}.';
$lang['ra_summary_weekly_one'] = 'jede Woche am {days}';
$lang['ra_summary_monthly_weekday_one'] = 'jeden Monat am {nth} {weekday}';
$lang['ra_summary_monthly_day_one'] = 'jeden Monat am {day}.';
$lang['ra_summary_until'] = 'bis {date}';
$lang['ra_summary_count'] = '{count} Mal';

$lang['ra_series_created'] = '{created} Termine angelegt.';
$lang['ra_series_created_one'] = '1 Termin angelegt.';
$lang['ra_series_skipped'] = '{skipped} Termine mit Konflikt übersprungen.';
$lang['ra_series_skipped_one'] = '1 Termin mit Konflikt übersprungen.';
$lang['ra_series_updated'] = '{updated} Termine aktualisiert.';
$lang['ra_series_updated_one'] = '1 Termin aktualisiert.';
$lang['ra_series_deleted'] = '{deleted} Termine gelöscht.';
$lang['ra_series_deleted_one'] = '1 Termin gelöscht.';
$lang['ra_detached'] = 'Der Termin gehört nicht mehr zur Serie.';

$lang['ra_conflicts_intro'] = 'Konflikt mit dem Plan des Dienstleisters an {count} von {total} Terminen:';
$lang['ra_no_conflicts'] = 'Keine Konflikte. Diese Termine werden gebucht ({total}):';
$lang['ra_skip_conflicts'] = 'Konflikte überspringen';
$lang['ra_book_anyway'] = 'Trotzdem buchen';
$lang['ra_conflict_day_off'] = 'Der Dienstleister arbeitet an diesem Tag nicht.';
$lang['ra_conflict_outside_hours'] = 'Außerhalb der Arbeitszeit ({start} - {end}).';
$lang['ra_conflict_break'] = 'Überschneidet eine Pause ({start} - {end}).';
$lang['ra_conflict_appointment'] = 'Überschneidet einen anderen Termin ({start} - {end}).';
$lang['ra_conflict_unavailability'] = 'Überschneidet eine Abwesenheit ({start} - {end}).';
$lang['ra_conflict_blocked_period'] = 'Liegt im gesperrten Zeitraum „{name}“.';
$lang['ra_conflict_same_series'] = 'Überschneidet Termin #{sequence} dieser Serie.';

$lang['ra_series_info'] = 'Termin {position} von {total} eines Serientermins ({summary}).';
$lang['ra_series_hint'] = '„Speichern“ ändert nur diesen Termin.';
$lang['ra_series_marker'] = 'Serientermin';
$lang['ra_save_following'] = 'Diesen und folgende speichern';
$lang['ra_save_all'] = 'Alle speichern';
$lang['ra_detach'] = 'Aus Serie lösen';
$lang['ra_delete_single'] = 'Nur diesen löschen';
$lang['ra_delete_following'] = 'Diesen und folgende löschen';
$lang['ra_delete_all'] = 'Ganze Serie löschen';
$lang['ra_confirm_delete'] = '{scope}? Dies kann nicht rückgängig gemacht werden.';
$lang['ra_title'] = 'Serientermin';

$lang['ra_not_installed'] = 'Die Erweiterung für Serientermine ist noch nicht eingerichtet: die Datenbanktabellen fehlen.';
$lang['ra_install_now'] = 'Jetzt einrichten';
$lang['ra_installed'] = 'Serientermine sind einsatzbereit.';

$lang['ra_error_generic'] = 'Etwas ist schiefgelaufen: {message}';
$lang['ra_error_permission'] = 'Keine Berechtigung für diese Aktion. Ein Administrator kann die Rechte Ihrer Rolle prüfen.';
$lang['ra_error_session'] = 'Die Sitzung ist abgelaufen. Laden Sie die Seite neu und melden Sie sich erneut an.';
$lang['ra_error_server'] = 'Der Server hat unerwartet geantwortet. Laden Sie die Seite neu und versuchen Sie es erneut. Passiert es weiterhin, kann ein Administrator die Protokolle in storage/logs prüfen.';
$lang['ra_error_invalid_request'] = 'Das Formular war unvollständig ({detail}). Laden Sie die Seite neu und versuchen Sie es erneut.';
$lang['ra_error_frequency'] = 'Dieses Wiederholungsmuster wird nicht unterstützt.';
$lang['ra_error_interval'] = 'Das Intervall muss zwischen 1 und {max} liegen.';
$lang['ra_error_weekday'] = 'Bitte einen gültigen Wochentag wählen.';
$lang['ra_error_nth'] = 'Bitte eine gültige Woche des Monats wählen.';
$lang['ra_error_day'] = 'Der Tag des Monats muss zwischen 1 und 31 liegen.';
$lang['ra_error_end_condition'] = 'Bitte ein Enddatum oder eine Anzahl wählen.';
$lang['ra_error_until_before_start'] = 'Das Enddatum darf nicht vor dem Startdatum liegen.';
$lang['ra_error_count'] = 'Die Anzahl muss zwischen 1 und {max} liegen.';
$lang['ra_error_too_many'] = 'Das ergäbe mehr als {max} Termine, bitte ein früheres Enddatum wählen.';
$lang['ra_error_invalid_date'] = 'Ungültiges Datum: {value}.';
$lang['ra_error_number'] = 'Ganze Zahl erwartet: {value}.';
$lang['ra_error_no_occurrences'] = 'Das Muster ergibt bis zum Ende keinen Termin. Prüfen Sie die gewählten Wochentage und das Enddatum.';
$lang['ra_error_all_conflict'] = 'Alle Termine kollidieren mit dem Plan des Dienstleisters, es wurde nichts angelegt. Wählen Sie eine andere Uhrzeit oder „Trotzdem buchen“.';
$lang['ra_error_not_series'] = 'Dieser Termin gehört nicht (mehr) zu einer Serie. Vielleicht wurde er gelöst oder gelöscht; laden Sie den Kalender neu.';
$lang['ra_error_not_installed'] = 'Die Erweiterung für Serientermine ist noch nicht eingerichtet. Ein Administrator kann auf der Kalenderseite auf „Jetzt einrichten“ klicken.';
$lang['ra_error_no_customer'] = 'Es wurde kein Kunde ausgewählt.';
$lang['ra_error_existing'] = 'Eine neue Serie kann nicht aus einem bestehenden Termin erstellt werden.';
$lang['ra_error_staff_disabled'] = 'Das Anlegen von Serienterminen ist ausgeschaltet. Ein Administrator kann es unter Einstellungen → Buchung einschalten.';
$lang['ra_error_duration'] = 'Der Termin muss mindestens {min} Minuten dauern.';

// Settings → Booking.
$lang['ra_settings_title'] = 'Serientermine';
$lang['ra_settings_staff_enabled'] = 'Mitarbeiter können im Kalender Serientermine anlegen';
$lang['ra_settings_staff_default_on'] = '„Diesen Termin wiederholen“ bei neuen Terminen standardmäßig einschalten';
$lang['ra_settings_booking_enabled'] = 'Kunden können auf der Buchungsseite einen Serientermin buchen';
$lang['ra_settings_booking_hint'] = 'Kunden wählen jede Woche, alle 2 Wochen, alle 4 Wochen oder jeden Monat. Jeder Termin wird gegen die normale Verfügbarkeit geprüft. Belegte Termine werden übersprungen und dem Kunden nach der Buchung angezeigt.';
$lang['ra_settings_booking_max'] = 'Höchstens Termine pro Buchung (der erste mitgezählt)';
$lang['ra_settings_booking_notify'] = 'Bestätigungs-E-Mails';
$lang['ra_settings_booking_notify_all'] = 'Eine pro Termin (jede mit eigenem Link zum Absagen und Verschieben)';
$lang['ra_settings_booking_notify_first'] = 'Nur für den ersten Termin';
$lang['ra_settings_save_hint'] = 'Wird mit der Schaltfläche Speichern oben auf dieser Seite gespeichert.';

// Public booking page and confirmation page.
$lang['ra_booking_repeat'] = 'Wiederholen';
$lang['ra_booking_no_repeat'] = 'Keine Wiederholung';
$lang['ra_booking_weekly'] = 'Jede Woche';
$lang['ra_booking_every_2_weeks'] = 'Alle 2 Wochen';
$lang['ra_booking_every_4_weeks'] = 'Alle 4 Wochen';
$lang['ra_booking_monthly'] = 'Jeden Monat (gleicher Wochentag)';
$lang['ra_booking_count'] = 'Anzahl der Termine (dieser mitgezählt)';
$lang['ra_booking_hint'] = 'Nicht verfügbare Termine werden übersprungen. Nach der Buchung sehen Sie, welche Termine gebucht wurden.';
$lang['ra_booking_summary'] = '{pattern}, insgesamt {count} Termine';
$lang['ra_booking_booked_title'] = 'Ihr Serientermin: {count} Termine gebucht';
$lang['ra_booking_skipped_title'] = 'Nicht verfügbar, daher nicht gebucht:';
$lang['ra_booking_only_first'] = 'Die folgenden Termine waren nicht verfügbar, daher wurde nur dieser Termin gebucht.';
