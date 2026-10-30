<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * Nederlands. Ontbrekende sleutels vallen terug op het Engels.
 * ---------------------------------------------------------------------------- */

$lang['ra_repeat'] = 'Herhalen';
$lang['ra_repeat_this'] = 'Deze afspraak herhalen';
$lang['ra_frequency_weekly'] = 'Wekelijks';
$lang['ra_frequency_monthly_weekday'] = 'Maandelijks op een weekdag (bijv. eerste maandag)';
$lang['ra_frequency_monthly_day'] = 'Maandelijks op een dagnummer (bijv. de 15e)';
$lang['ra_every'] = 'Elke';
$lang['ra_weeks'] = 'week/weken';
$lang['ra_months'] = 'maand(en)';
$lang['ra_on'] = 'Op';
$lang['ra_the'] = 'De';
$lang['ra_day'] = 'Dag';
$lang['ra_ends'] = 'Eindigt';
$lang['ra_after'] = 'Na';
$lang['ra_occurrences'] = 'keer';
$lang['ra_on_date'] = 'Op datum';
$lang['ra_nth_1'] = 'eerste';
$lang['ra_nth_2'] = 'tweede';
$lang['ra_nth_3'] = 'derde';
$lang['ra_nth_4'] = 'vierde';
$lang['ra_nth_5'] = 'vijfde';
$lang['ra_nth_last'] = 'laatste';
$lang['ra_preview'] = 'Data bekijken';
$lang['ra_notify'] = 'Klant en medewerker informeren';
$lang['ra_notify_none'] = 'Nee';
$lang['ra_notify_first'] = 'Eén keer (eerste afspraak)';
$lang['ra_notify_all'] = 'Voor elke afspraak';

$lang['ra_summary_weekly'] = 'elke {interval} week/weken op {days}';
$lang['ra_summary_monthly_weekday'] = 'elke {interval} maand(en) op de {nth} {weekday}';
$lang['ra_summary_monthly_day'] = 'elke {interval} maand(en) op dag {day}';
$lang['ra_summary_weekly_one'] = 'elke week op {days}';
$lang['ra_summary_monthly_weekday_one'] = 'elke maand op de {nth} {weekday}';
$lang['ra_summary_monthly_day_one'] = 'elke maand op dag {day}';
$lang['ra_summary_until'] = 'tot {date}';
$lang['ra_summary_count'] = '{count} keer';

$lang['ra_series_created'] = '{created} afspraken aangemaakt.';
$lang['ra_series_created_one'] = '1 afspraak aangemaakt.';
$lang['ra_series_skipped'] = '{skipped} data met een conflict overgeslagen.';
$lang['ra_series_skipped_one'] = '1 datum met een conflict overgeslagen.';
$lang['ra_series_updated'] = '{updated} afspraken bijgewerkt.';
$lang['ra_series_updated_one'] = '1 afspraak bijgewerkt.';
$lang['ra_series_deleted'] = '{deleted} afspraken verwijderd.';
$lang['ra_series_deleted_one'] = '1 afspraak verwijderd.';
$lang['ra_detached'] = 'De afspraak hoort niet meer bij de reeks.';

$lang['ra_conflicts_intro'] = 'Conflict met de planning van de medewerker op {count} van de {total} data:';
$lang['ra_no_conflicts'] = 'Geen conflicten. Deze data worden ingepland ({total}):';
$lang['ra_skip_conflicts'] = 'Conflicten overslaan';
$lang['ra_book_anyway'] = 'Toch boeken';
$lang['ra_conflict_day_off'] = 'De medewerker werkt niet op deze dag.';
$lang['ra_conflict_outside_hours'] = 'Buiten werktijd ({start} - {end}).';
$lang['ra_conflict_break'] = 'Valt in een pauze ({start} - {end}).';
$lang['ra_conflict_appointment'] = 'Overlapt met een andere afspraak ({start} - {end}).';
$lang['ra_conflict_unavailability'] = 'Overlapt met een afwezigheid ({start} - {end}).';
$lang['ra_conflict_blocked_period'] = 'Valt in de geblokkeerde periode "{name}".';
$lang['ra_conflict_same_series'] = 'Overlapt met afspraak #{sequence} van deze reeks.';

$lang['ra_series_info'] = 'Afspraak {position} van {total} van een herhalende afspraak ({summary}).';
$lang['ra_series_hint'] = '"Opslaan" wijzigt alleen deze afspraak.';
$lang['ra_series_marker'] = 'Herhalende afspraak';
$lang['ra_save_following'] = 'Deze en volgende opslaan';
$lang['ra_save_all'] = 'Alle opslaan';
$lang['ra_detach'] = 'Loskoppelen van reeks';
$lang['ra_delete_single'] = 'Alleen deze verwijderen';
$lang['ra_delete_following'] = 'Deze en volgende verwijderen';
$lang['ra_delete_all'] = 'Hele reeks verwijderen';
$lang['ra_confirm_delete'] = '{scope}? Dit kan niet ongedaan worden gemaakt.';
$lang['ra_title'] = 'Herhalende afspraak';

$lang['ra_not_installed'] = 'De extensie voor herhalende afspraken is nog niet ingesteld: de databasetabellen ontbreken.';
$lang['ra_install_now'] = 'Nu instellen';
$lang['ra_installed'] = 'Herhalende afspraken zijn klaar voor gebruik.';

$lang['ra_error_generic'] = 'Er ging iets mis: {message}';
$lang['ra_error_permission'] = 'Geen toestemming voor deze actie. Een beheerder kan de rechten van de gebruikersrol controleren.';
$lang['ra_error_session'] = 'De sessie is verlopen. Herlaad de pagina en log opnieuw in.';
$lang['ra_error_server'] = 'De server gaf een onverwacht antwoord. Herlaad de pagina en probeer het opnieuw. Blijft het gebeuren, dan kan een beheerder de logbestanden in storage/logs bekijken.';
$lang['ra_error_invalid_request'] = 'Het formulier was niet compleet ({detail}). Herlaad de pagina en probeer het opnieuw.';
$lang['ra_error_frequency'] = 'Dit herhaalpatroon wordt niet ondersteund.';
$lang['ra_error_interval'] = 'De herhaling moet tussen 1 en {max} liggen.';
$lang['ra_error_weekday'] = 'Kies een geldige weekdag.';
$lang['ra_error_nth'] = 'Kies een geldige week van de maand.';
$lang['ra_error_day'] = 'De dag van de maand moet tussen 1 en 31 liggen.';
$lang['ra_error_end_condition'] = 'Kies een einddatum of een aantal keer.';
$lang['ra_error_until_before_start'] = 'De einddatum kan niet voor de startdatum liggen.';
$lang['ra_error_count'] = 'Het aantal keer moet tussen 1 en {max} liggen.';
$lang['ra_error_too_many'] = 'Dit zou meer dan {max} afspraken opleveren, kies een eerdere einddatum.';
$lang['ra_error_invalid_date'] = 'Ongeldige datum: {value}.';
$lang['ra_error_number'] = 'Verwacht een heel getal: {value}.';
$lang['ra_error_no_occurrences'] = 'Het herhaalpatroon levert geen datum op voor het einde. Controleer de gekozen weekdagen en de einddatum.';
$lang['ra_error_all_conflict'] = 'Alle data botsen met de planning van de medewerker, er is niets aangemaakt. Kies een ander tijdstip of gebruik "Toch boeken".';
$lang['ra_error_not_series'] = 'Deze afspraak hoort niet (meer) bij een herhalende reeks. Misschien is ze losgekoppeld of verwijderd; herlaad de kalender.';
$lang['ra_error_not_installed'] = 'De extensie voor herhalende afspraken is nog niet ingesteld. Een beheerder kan op de kalenderpagina op "Nu instellen" klikken.';
$lang['ra_error_no_customer'] = 'Er is geen klant gekozen.';
$lang['ra_error_existing'] = 'Een nieuwe reeks kan niet vanuit een bestaande afspraak worden gemaakt.';
$lang['ra_error_staff_disabled'] = 'Herhalende afspraken maken staat uit. Een beheerder kan dit aanzetten via Instellingen → Boeking.';
$lang['ra_error_duration'] = 'De afspraak moet minstens {min} minuten duren.';

// Settings → Booking.
$lang['ra_settings_title'] = 'Herhalende afspraken';
$lang['ra_settings_staff_enabled'] = 'Medewerkers kunnen herhalende afspraken maken in de kalender';
$lang['ra_settings_staff_default_on'] = '"Deze afspraak herhalen" standaard aanzetten bij nieuwe afspraken';
$lang['ra_settings_booking_enabled'] = 'Klanten kunnen op de boekingspagina een herhalende afspraak boeken';
$lang['ra_settings_booking_hint'] = 'Klanten kiezen elke week, elke 2 weken, elke 4 weken of elke maand. Elke datum wordt gecontroleerd op de normale beschikbaarheid. Data die niet vrij zijn worden overgeslagen en na het boeken aan de klant getoond.';
$lang['ra_settings_booking_max'] = 'Maximaal aantal afspraken per boeking (de eerste meegeteld)';
$lang['ra_settings_booking_notify'] = 'Bevestigingsmails';
$lang['ra_settings_booking_notify_all'] = 'Eén per afspraak (elk met een eigen link om te annuleren of te verzetten)';
$lang['ra_settings_booking_notify_first'] = 'Alleen voor de eerste afspraak';
$lang['ra_settings_save_hint'] = 'Wordt opgeslagen met de knop Opslaan bovenaan deze pagina.';

// Public booking page and confirmation page.
$lang['ra_booking_repeat'] = 'Herhalen';
$lang['ra_booking_no_repeat'] = 'Niet herhalen';
$lang['ra_booking_weekly'] = 'Elke week';
$lang['ra_booking_every_2_weeks'] = 'Elke 2 weken';
$lang['ra_booking_every_4_weeks'] = 'Elke 4 weken';
$lang['ra_booking_monthly'] = 'Elke maand (zelfde weekdag)';
$lang['ra_booking_count'] = 'Aantal afspraken (deze meegeteld)';
$lang['ra_booking_hint'] = 'Data die niet beschikbaar zijn worden overgeslagen. Na het boeken zie je welke data geboekt zijn.';
$lang['ra_booking_summary'] = '{pattern}, in totaal {count} afspraken';
$lang['ra_booking_booked_title'] = 'Je herhalende afspraak: {count} afspraken geboekt';
$lang['ra_booking_skipped_title'] = 'Niet beschikbaar, dus niet geboekt:';
$lang['ra_booking_only_first'] = 'De volgende data waren niet beschikbaar, daarom is alleen deze afspraak geboekt.';
