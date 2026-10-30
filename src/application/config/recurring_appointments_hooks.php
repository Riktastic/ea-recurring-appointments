<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * Hook registration, included from application/config/hooks.php with:
 *
 *     if (is_file(APPPATH . 'config/recurring_appointments_hooks.php')) { include APPPATH . 'config/recurring_appointments_hooks.php'; }
 *
 * The is_file() guard keeps Easy!Appointments working when the extension files are removed again.
 * ---------------------------------------------------------------------------- */

$hook['display_override'][] = [
    'class' => '',
    'function' => 'recurring_appointments_inject_script',
    'filename' => 'recurring_appointments.php',
    'filepath' => 'hooks',
    'params' => [],
];
