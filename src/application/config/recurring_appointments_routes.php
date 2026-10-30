<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Recurring Appointments extension
 *
 * REST API routes for appointment series, included at the end of application/config/routes.php by one guarded line
 * that the installer adds (and removes on uninstall). See controllers/api/v1/Appointment_series_api_v1.php.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

$route['api/v1/appointment_series']['get'] = 'api/v1/appointment_series_api_v1/index';
$route['api/v1/appointment_series']['post'] = 'api/v1/appointment_series_api_v1/store';
$route['api/v1/appointment_series/(:num)']['get'] = 'api/v1/appointment_series_api_v1/show/$1';
$route['api/v1/appointment_series/(:num)']['put'] = 'api/v1/appointment_series_api_v1/update/$1';
$route['api/v1/appointment_series/(:num)']['delete'] = 'api/v1/appointment_series_api_v1/destroy/$1';
