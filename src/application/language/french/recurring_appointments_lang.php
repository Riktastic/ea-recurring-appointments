<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * Français. Les clés manquantes utilisent l'anglais.
 * ---------------------------------------------------------------------------- */

$lang['ra_repeat'] = 'Répéter';
$lang['ra_repeat_this'] = 'Répéter ce rendez-vous';
$lang['ra_frequency_daily'] = 'Quotidien';
$lang['ra_frequency_yearly'] = 'Annuel (même date)';
$lang['ra_frequency_weekly'] = 'Chaque semaine';
$lang['ra_frequency_monthly_weekday'] = 'Chaque mois un jour de semaine (ex. premier lundi)';
$lang['ra_frequency_monthly_day'] = 'Chaque mois à une date (ex. le 15)';
$lang['ra_every'] = 'Toutes les';
$lang['ra_weeks'] = 'semaine(s)';
$lang['ra_months'] = 'mois';
$lang['ra_days'] = 'jour(s)';
$lang['ra_years'] = 'an(s)';
$lang['ra_on'] = 'Le';
$lang['ra_the'] = 'Le';
$lang['ra_day'] = 'Jour';
$lang['ra_ends'] = 'Fin';
$lang['ra_after'] = 'Après';
$lang['ra_occurrences'] = 'rendez-vous';
$lang['ra_on_date'] = 'Le';
$lang['ra_nth_1'] = 'premier';
$lang['ra_nth_2'] = 'deuxième';
$lang['ra_nth_3'] = 'troisième';
$lang['ra_nth_4'] = 'quatrième';
$lang['ra_nth_5'] = 'cinquième';
$lang['ra_nth_last'] = 'dernier';
$lang['ra_preview'] = 'Voir les dates';
$lang['ra_notify'] = 'Prévenir le client et le prestataire';
$lang['ra_notify_none'] = 'Non';
$lang['ra_notify_first'] = 'Une fois (premier rendez-vous)';
$lang['ra_notify_all'] = 'Pour chaque rendez-vous';

$lang['ra_summary_weekly'] = 'toutes les {interval} semaine(s) le {days}';
$lang['ra_summary_monthly_weekday'] = 'tous les {interval} mois le {nth} {weekday}';
$lang['ra_summary_monthly_day'] = 'tous les {interval} mois le {day}';
$lang['ra_summary_weekly_one'] = 'chaque semaine le {days}';
$lang['ra_summary_monthly_weekday_one'] = 'chaque mois le {nth} {weekday}';
$lang['ra_summary_monthly_day_one'] = 'chaque mois le {day}';
$lang['ra_summary_daily'] = 'tous les {interval} jours';
$lang['ra_summary_daily_one'] = 'chaque jour';
$lang['ra_summary_yearly'] = 'tous les {interval} ans le {date}';
$lang['ra_summary_yearly_one'] = 'chaque année le {date}';
$lang['ra_summary_until'] = "jusqu'au {date}";
$lang['ra_summary_count'] = '{count} fois';

$lang['ra_series_created'] = '{created} rendez-vous créés.';
$lang['ra_series_created_one'] = '1 rendez-vous créé.';
$lang['ra_series_skipped'] = '{skipped} dates en conflit ignorées.';
$lang['ra_series_skipped_one'] = '1 date en conflit ignorée.';
$lang['ra_series_updated'] = '{updated} rendez-vous mis à jour.';
$lang['ra_series_updated_one'] = '1 rendez-vous mis à jour.';
$lang['ra_series_deleted'] = '{deleted} rendez-vous supprimés.';
$lang['ra_series_deleted_one'] = '1 rendez-vous supprimé.';
$lang['ra_detached'] = 'Le rendez-vous ne fait plus partie de la série.';

$lang['ra_conflicts_intro'] = 'Conflit avec le planning du prestataire pour {count} des {total} dates :';
$lang['ra_no_conflicts'] = 'Aucun conflit. Dates qui seront réservées ({total}) :';
$lang['ra_skip_conflicts'] = 'Ignorer les conflits';
$lang['ra_book_anyway'] = 'Réserver quand même';
$lang['ra_conflict_day_off'] = 'Le prestataire ne travaille pas ce jour-là.';
$lang['ra_conflict_outside_hours'] = 'En dehors des heures de travail ({start} - {end}).';
$lang['ra_conflict_break'] = 'Chevauche une pause ({start} - {end}).';
$lang['ra_conflict_appointment'] = 'Chevauche un autre rendez-vous ({start} - {end}).';
$lang['ra_conflict_unavailability'] = 'Chevauche une indisponibilité ({start} - {end}).';
$lang['ra_conflict_blocked_period'] = 'Tombe dans la période bloquée « {name} ».';
$lang['ra_conflict_same_series'] = 'Chevauche le rendez-vous n°{sequence} de cette série.';

$lang['ra_series_info'] = 'Rendez-vous {position} sur {total} d\'une série ({summary}).';
$lang['ra_series_hint'] = '« Enregistrer » ne modifie que ce rendez-vous.';
$lang['ra_series_marker'] = 'Rendez-vous récurrent';
$lang['ra_save_following'] = 'Enregistrer celui-ci et les suivants';
$lang['ra_save_all'] = 'Enregistrer tous';
$lang['ra_edit_series'] = 'Modifier la série';
$lang['ra_edit_series_hint'] = 'Modifiez l\'heure ou les détails ci-dessous, puis cliquez sur « Enregistrer tous » ou « Enregistrer celui-ci et les suivants ».';
$lang['ra_detach'] = 'Détacher de la série';
$lang['ra_delete_single'] = 'Supprimer celui-ci';
$lang['ra_delete_following'] = 'Supprimer celui-ci et les suivants';
$lang['ra_delete_all'] = 'Supprimer toute la série';
$lang['ra_confirm_delete'] = '{scope} ? Cette action est irréversible.';
$lang['ra_title'] = 'Rendez-vous récurrent';

$lang['ra_not_installed'] = "L'extension des rendez-vous récurrents n'est pas encore configurée : ses tables sont absentes.";
$lang['ra_install_now'] = 'Configurer maintenant';
$lang['ra_installed'] = 'Les rendez-vous récurrents sont prêts.';

$lang['ra_error_generic'] = "Une erreur s'est produite : {message}";
$lang['ra_error_permission'] = 'Action non autorisée. Un administrateur peut vérifier les droits de votre rôle.';
$lang['ra_error_session'] = 'La session a expiré. Rechargez la page et reconnectez-vous.';
$lang['ra_error_server'] = 'Le serveur a répondu de façon inattendue. Rechargez la page et réessayez. Si cela continue, un administrateur peut consulter les journaux dans storage/logs.';
$lang['ra_error_invalid_request'] = 'Le formulaire était incomplet ({detail}). Rechargez la page et réessayez.';
$lang['ra_error_frequency'] = "Ce modèle de répétition n'est pas pris en charge.";
$lang['ra_error_interval'] = "L'intervalle doit être compris entre 1 et {max}.";
$lang['ra_error_weekday'] = 'Choisissez un jour de semaine valide.';
$lang['ra_error_nth'] = 'Choisissez une semaine du mois valide.';
$lang['ra_error_day'] = 'Le jour du mois doit être compris entre 1 et 31.';
$lang['ra_error_end_condition'] = 'Choisissez une date de fin ou un nombre de rendez-vous.';
$lang['ra_error_until_before_start'] = 'La date de fin ne peut pas précéder la date de début.';
$lang['ra_error_count'] = 'Le nombre doit être compris entre 1 et {max}.';
$lang['ra_error_too_many'] = 'Cela créerait plus de {max} rendez-vous, choisissez une date de fin plus proche.';
$lang['ra_error_invalid_date'] = 'Date invalide : {value}.';
$lang['ra_error_number'] = 'Nombre entier attendu : {value}.';
$lang['ra_error_no_occurrences'] = 'Le motif ne donne aucune date avant la fin. Vérifiez les jours choisis et la date de fin.';
$lang['ra_error_all_conflict'] = 'Toutes les dates sont en conflit avec le planning du prestataire, rien n\'a été créé. Choisissez une autre heure ou « Réserver quand même ».';
$lang['ra_error_not_series'] = 'Ce rendez-vous ne fait plus partie d\'une série. Il a peut-être été détaché ou supprimé ; rechargez le calendrier.';
$lang['ra_error_not_installed'] = 'L\'extension des rendez-vous récurrents n\'est pas encore configurée. Un administrateur peut cliquer sur « Configurer maintenant » dans le calendrier.';
$lang['ra_error_no_customer'] = "Aucun client n'a été sélectionné.";
$lang['ra_error_existing'] = "Une nouvelle série ne peut pas être créée à partir d'un rendez-vous existant.";
$lang['ra_error_staff_disabled'] = 'La création de rendez-vous récurrents est désactivée. Un administrateur peut l\'activer dans Paramètres → Réservation.';
$lang['ra_error_duration'] = 'Le rendez-vous doit durer au moins {min} minutes.';

// Settings → Booking.
$lang['ra_settings_title'] = 'Rendez-vous récurrents';
$lang['ra_settings_staff_enabled'] = 'Le personnel peut créer des rendez-vous récurrents dans le calendrier';
$lang['ra_settings_staff_default_on'] = 'Activer « Répéter ce rendez-vous » par défaut pour les nouveaux rendez-vous';
$lang['ra_settings_max_occurrences'] = 'Nombre maximal de rendez-vous dans une série';
$lang['ra_settings_max_occurrences_hint'] = 'Protège contre une faute de frappe dans la date de fin. 400 suffit pour une série quotidienne sur un an ; 1000 au maximum.';
$lang['ra_settings_booking_enabled'] = 'Les clients peuvent réserver un rendez-vous récurrent sur la page de réservation';
$lang['ra_settings_booking_hint'] = 'Les clients choisissent chaque semaine, toutes les 2 semaines, toutes les 4 semaines ou chaque mois. Chaque date est vérifiée selon la disponibilité normale. Les dates non libres sont ignorées et affichées au client après la réservation.';
$lang['ra_settings_booking_max'] = 'Nombre maximal de rendez-vous par réservation (le premier compris)';
$lang['ra_settings_booking_notify'] = 'E-mails de confirmation';
$lang['ra_settings_booking_notify_all'] = 'Un par rendez-vous (chacun avec son propre lien d\'annulation et de report)';
$lang['ra_settings_booking_notify_first'] = 'Uniquement pour le premier rendez-vous';
$lang['ra_settings_save_hint'] = 'Enregistré avec le bouton Enregistrer en haut de cette page.';

// Public booking page and confirmation page.
$lang['ra_booking_repeat'] = 'Répéter';
$lang['ra_booking_no_repeat'] = 'Pas de répétition';
$lang['ra_booking_daily'] = 'Chaque jour';
$lang['ra_booking_weekly'] = 'Chaque semaine';
$lang['ra_booking_every_2_weeks'] = 'Toutes les 2 semaines';
$lang['ra_booking_every_4_weeks'] = 'Toutes les 4 semaines';
$lang['ra_booking_monthly'] = 'Chaque mois (même jour de la semaine)';
$lang['ra_booking_yearly'] = 'Chaque année (même date)';
$lang['ra_booking_count'] = 'Nombre de rendez-vous (celui-ci compris)';
$lang['ra_booking_weekdays'] = 'Ces jours-là';
$lang['ra_booking_until'] = 'Jusqu\'au (date)';
$lang['ra_booking_hint_max'] = '{max} rendez-vous au maximum. Les dates non disponibles sont ignorées. Après la réservation, vous voyez les dates réservées.';
$lang['ra_booking_hint'] = 'Les dates non disponibles sont ignorées. Après la réservation, vous voyez les dates réservées.';
$lang['ra_booking_summary'] = '{pattern}, {count} rendez-vous au total';
$lang['ra_booking_summary_until'] = '{pattern}, jusqu\'au {date}';
$lang['ra_booking_booked_title'] = 'Votre rendez-vous récurrent : {count} rendez-vous réservés';
$lang['ra_booking_skipped_title'] = 'Non disponible, donc non réservé :';
$lang['ra_booking_only_first'] = 'Les dates suivantes n\'étaient pas disponibles, seul ce rendez-vous a donc été réservé.';
$lang['ra_booking_capped'] = 'Seuls les {max} premiers rendez-vous ont été réservés : c\'est le maximum par réservation.';
