-- Easy!Appointments - Recurring Appointments extension
--
-- Manual alternative to "php index.php recurring_appointments install" (or the "Set up now" button on the calendar).
-- Assumes the default table prefix "ea_"; replace it everywhere if your installation uses another prefix.
--
-- There are deliberately no foreign keys into the core tables, so that core migrations are never blocked. Links are
-- matched on appointment ID + hash, and links to deleted appointments are cleaned up automatically.

CREATE TABLE IF NOT EXISTS `ea_appointment_series` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `rule` TEXT NOT NULL,
    `start_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `duration` INT(11) NOT NULL,
    `id_users_provider` INT(11) NOT NULL,
    `id_users_customer` INT(11) NOT NULL,
    `id_services` INT(11) NOT NULL,
    `id_users_created_by` INT(11) NULL,
    `create_datetime` DATETIME NULL,
    `update_datetime` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `id_users_provider` (`id_users_provider`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `ea_appointment_series_occurrences` (
    `id_appointments` INT(11) NOT NULL,
    `appointment_hash` VARCHAR(64) NOT NULL,
    `id_appointment_series` INT(11) NOT NULL,
    `sequence` INT(11) NOT NULL,
    `occurrence_date` DATE NOT NULL,
    PRIMARY KEY (`id_appointments`),
    KEY `id_appointment_series` (`id_appointment_series`),
    CONSTRAINT `appointment_series_occurrences_series` FOREIGN KEY (`id_appointment_series`)
        REFERENCES `ea_appointment_series` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
