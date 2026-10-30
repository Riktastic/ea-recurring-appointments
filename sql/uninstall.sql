-- Easy!Appointments - Recurring Appointments extension
--
-- Drops the extension tables and removes its settings. The appointments in ea_appointments are kept and become regular single appointments.
-- Assumes the default table prefix "ea_".

DROP TABLE IF EXISTS `ea_appointment_series_occurrences`;
DROP TABLE IF EXISTS `ea_appointment_series`;
DELETE FROM `ea_settings` WHERE `name` LIKE 'recurring!_appointments!_%' ESCAPE '!';
