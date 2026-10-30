<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * Español. Las claves que faltan usan el inglés.
 * ---------------------------------------------------------------------------- */

$lang['ra_repeat'] = 'Repetir';
$lang['ra_repeat_this'] = 'Repetir esta cita';
$lang['ra_frequency_daily'] = 'Diario';
$lang['ra_frequency_yearly'] = 'Anual (misma fecha)';
$lang['ra_frequency_weekly'] = 'Semanal';
$lang['ra_frequency_monthly_weekday'] = 'Mensual en un día de la semana (p. ej. primer lunes)';
$lang['ra_frequency_monthly_day'] = 'Mensual en un día del mes (p. ej. el día 15)';
$lang['ra_every'] = 'Cada';
$lang['ra_weeks'] = 'semana(s)';
$lang['ra_months'] = 'mes(es)';
$lang['ra_days'] = 'día(s)';
$lang['ra_years'] = 'año(s)';
$lang['ra_on'] = 'El';
$lang['ra_the'] = 'El';
$lang['ra_day'] = 'Día';
$lang['ra_ends'] = 'Termina';
$lang['ra_after'] = 'Después de';
$lang['ra_occurrences'] = 'citas';
$lang['ra_on_date'] = 'En la fecha';
$lang['ra_nth_1'] = 'primer';
$lang['ra_nth_2'] = 'segundo';
$lang['ra_nth_3'] = 'tercer';
$lang['ra_nth_4'] = 'cuarto';
$lang['ra_nth_5'] = 'quinto';
$lang['ra_nth_last'] = 'último';
$lang['ra_preview'] = 'Ver fechas';
$lang['ra_notify'] = 'Notificar al cliente y al proveedor';
$lang['ra_notify_none'] = 'No';
$lang['ra_notify_first'] = 'Una vez (primera cita)';
$lang['ra_notify_all'] = 'Para cada cita';

$lang['ra_summary_weekly'] = 'cada {interval} semana(s) el {days}';
$lang['ra_summary_monthly_weekday'] = 'cada {interval} mes(es) el {nth} {weekday}';
$lang['ra_summary_monthly_day'] = 'cada {interval} mes(es) el día {day}';
$lang['ra_summary_weekly_one'] = 'cada semana el {days}';
$lang['ra_summary_monthly_weekday_one'] = 'cada mes el {nth} {weekday}';
$lang['ra_summary_monthly_day_one'] = 'cada mes el día {day}';
$lang['ra_summary_daily'] = 'cada {interval} días';
$lang['ra_summary_daily_one'] = 'cada día';
$lang['ra_summary_yearly'] = 'cada {interval} años el {date}';
$lang['ra_summary_yearly_one'] = 'cada año el {date}';
$lang['ra_summary_until'] = 'hasta el {date}';
$lang['ra_summary_count'] = '{count} veces';

$lang['ra_series_created'] = '{created} citas creadas.';
$lang['ra_series_created_one'] = '1 cita creada.';
$lang['ra_series_skipped'] = 'Se omitieron {skipped} fechas con conflicto.';
$lang['ra_series_skipped_one'] = 'Se omitió 1 fecha con conflicto.';
$lang['ra_series_updated'] = '{updated} citas actualizadas.';
$lang['ra_series_updated_one'] = '1 cita actualizada.';
$lang['ra_series_deleted'] = '{deleted} citas eliminadas.';
$lang['ra_series_deleted_one'] = '1 cita eliminada.';
$lang['ra_detached'] = 'La cita ya no forma parte de la serie.';

$lang['ra_conflicts_intro'] = 'Conflicto con la agenda del proveedor en {count} de las {total} fechas:';
$lang['ra_no_conflicts'] = 'Sin conflictos. Fechas que se reservarán ({total}):';
$lang['ra_skip_conflicts'] = 'Omitir conflictos';
$lang['ra_book_anyway'] = 'Reservar de todos modos';
$lang['ra_conflict_day_off'] = 'El proveedor no trabaja este día.';
$lang['ra_conflict_outside_hours'] = 'Fuera del horario laboral ({start} - {end}).';
$lang['ra_conflict_break'] = 'Coincide con un descanso ({start} - {end}).';
$lang['ra_conflict_appointment'] = 'Coincide con otra cita ({start} - {end}).';
$lang['ra_conflict_unavailability'] = 'Coincide con una ausencia ({start} - {end}).';
$lang['ra_conflict_blocked_period'] = 'Cae en el período bloqueado "{name}".';
$lang['ra_conflict_same_series'] = 'Coincide con la cita n.º {sequence} de esta serie.';

$lang['ra_series_info'] = 'Cita {position} de {total} de una cita periódica ({summary}).';
$lang['ra_series_hint'] = '"Guardar" solo cambia esta cita.';
$lang['ra_series_marker'] = 'Cita periódica';
$lang['ra_save_following'] = 'Guardar esta y las siguientes';
$lang['ra_save_all'] = 'Guardar todas';
$lang['ra_edit_series'] = 'Cambiar la serie';
$lang['ra_edit_series_hint'] = 'Cambie la hora o los datos abajo y pulse "Guardar todas" o "Guardar esta y las siguientes".';
$lang['ra_detach'] = 'Separar de la serie';
$lang['ra_delete_single'] = 'Eliminar solo esta';
$lang['ra_delete_following'] = 'Eliminar esta y las siguientes';
$lang['ra_delete_all'] = 'Eliminar toda la serie';
$lang['ra_confirm_delete'] = '¿{scope}? Esta acción no se puede deshacer.';
$lang['ra_title'] = 'Cita periódica';

$lang['ra_not_installed'] = 'La extensión de citas periódicas aún no está configurada: faltan sus tablas.';
$lang['ra_install_now'] = 'Configurar ahora';
$lang['ra_installed'] = 'Las citas periódicas están listas.';

$lang['ra_error_generic'] = 'Algo salió mal: {message}';
$lang['ra_error_permission'] = 'No tiene permiso para esta acción. Un administrador puede revisar los permisos de su rol.';
$lang['ra_error_session'] = 'La sesión ha caducado. Recargue la página e inicie sesión de nuevo.';
$lang['ra_error_server'] = 'El servidor respondió de forma inesperada. Recargue la página e inténtelo de nuevo. Si sigue ocurriendo, un administrador puede revisar los registros en storage/logs.';
$lang['ra_error_invalid_request'] = 'El formulario estaba incompleto ({detail}). Recargue la página e inténtelo de nuevo.';
$lang['ra_error_frequency'] = 'Este patrón de repetición no es compatible.';
$lang['ra_error_interval'] = 'El intervalo debe estar entre 1 y {max}.';
$lang['ra_error_weekday'] = 'Elija un día de la semana válido.';
$lang['ra_error_nth'] = 'Elija una semana del mes válida.';
$lang['ra_error_day'] = 'El día del mes debe estar entre 1 y 31.';
$lang['ra_error_end_condition'] = 'Elija una fecha de fin o un número de citas.';
$lang['ra_error_until_before_start'] = 'La fecha de fin no puede ser anterior a la de inicio.';
$lang['ra_error_count'] = 'El número de citas debe estar entre 1 y {max}.';
$lang['ra_error_too_many'] = 'Se crearían más de {max} citas, elija una fecha de fin anterior.';
$lang['ra_error_invalid_date'] = 'Fecha no válida: {value}.';
$lang['ra_error_number'] = 'Se esperaba un número entero: {value}.';
$lang['ra_error_no_occurrences'] = 'El patrón no da ninguna fecha antes del final. Revise los días elegidos y la fecha de fin.';
$lang['ra_error_all_conflict'] = 'Todas las fechas tienen conflicto con la agenda del proveedor, no se creó nada. Elija otra hora o use "Reservar de todos modos".';
$lang['ra_error_not_series'] = 'Esta cita ya no forma parte de una serie. Puede que se haya separado o eliminado; recargue el calendario.';
$lang['ra_error_not_installed'] = 'La extensión de citas periódicas aún no está configurada. Un administrador puede pulsar "Configurar ahora" en el calendario.';
$lang['ra_error_no_customer'] = 'No se seleccionó ningún cliente.';
$lang['ra_error_existing'] = 'No se puede crear una serie nueva a partir de una cita existente.';
$lang['ra_error_staff_disabled'] = 'La creación de citas periódicas está desactivada. Un administrador puede activarla en Configuración → Reserva.';
$lang['ra_error_duration'] = 'La cita debe durar al menos {min} minutos.';

// Settings → Booking.
$lang['ra_settings_title'] = 'Citas periódicas';
$lang['ra_settings_staff_enabled'] = 'El personal puede crear citas periódicas en el calendario';
$lang['ra_settings_staff_default_on'] = 'Activar "Repetir esta cita" por defecto en las citas nuevas';
$lang['ra_settings_max_occurrences'] = 'Máximo de citas en una serie';
$lang['ra_settings_max_occurrences_hint'] = 'Protege contra un error en la fecha de fin. 400 basta para una serie diaria durante un año; como máximo 1000.';
$lang['ra_settings_booking_enabled'] = 'Los clientes pueden reservar una cita periódica en la página de reservas';
$lang['ra_settings_booking_hint'] = 'Los clientes eligen cada semana, cada 2 semanas, cada 4 semanas o cada mes. Cada fecha se comprueba con la disponibilidad normal. Las fechas no libres se omiten y se muestran al cliente después de reservar.';
$lang['ra_settings_booking_max'] = 'Máximo de citas por reserva (la primera incluida)';
$lang['ra_settings_booking_notify'] = 'Correos de confirmación';
$lang['ra_settings_booking_notify_all'] = 'Uno por cita (cada uno con su propio enlace para cancelar o cambiar)';
$lang['ra_settings_booking_notify_first'] = 'Solo para la primera cita';
$lang['ra_settings_save_hint'] = 'Se guarda con el botón Guardar en la parte superior de esta página.';

// Public booking page and confirmation page.
$lang['ra_booking_repeat'] = 'Repetir';
$lang['ra_booking_no_repeat'] = 'No se repite';
$lang['ra_booking_daily'] = 'Cada día';
$lang['ra_booking_weekly'] = 'Cada semana';
$lang['ra_booking_every_2_weeks'] = 'Cada 2 semanas';
$lang['ra_booking_every_4_weeks'] = 'Cada 4 semanas';
$lang['ra_booking_monthly'] = 'Cada mes (mismo día de la semana)';
$lang['ra_booking_yearly'] = 'Cada año (misma fecha)';
$lang['ra_booking_count'] = 'Número de citas (esta incluida)';
$lang['ra_booking_weekdays'] = 'Estos días';
$lang['ra_booking_until'] = 'Hasta (fecha)';
$lang['ra_booking_hint_max'] = 'Como máximo {max} citas. Las fechas no disponibles se omiten. Después de reservar verá qué fechas se reservaron.';
$lang['ra_booking_hint'] = 'Las fechas no disponibles se omiten. Después de reservar verá qué fechas se reservaron.';
$lang['ra_booking_summary'] = '{pattern}, {count} citas en total';
$lang['ra_booking_summary_until'] = '{pattern}, hasta el {date}';
$lang['ra_booking_booked_title'] = 'Su cita periódica: {count} citas reservadas';
$lang['ra_booking_skipped_title'] = 'No disponible, por lo que no se reservó:';
$lang['ra_booking_only_first'] = 'Las fechas siguientes no estaban disponibles, por lo que solo se reservó esta cita.';
$lang['ra_booking_capped'] = 'Solo se reservaron las primeras {max} citas: es el máximo por reserva.';
