<?php

/* ----------------------------------------------------------------------------
 * Easy!Appointments config.php for the throwaway end-to-end test environment.
 *
 * Copy this over the config.php of the Easy!Appointments COPY that EA_PATH points to.
 * ---------------------------------------------------------------------------- */

class Config
{
    const BASE_URL = 'http://localhost:8089';
    const LANGUAGE = 'dutch';
    const DEBUG_MODE = false;

    const DB_HOST = 'db';
    const DB_NAME = 'easyappointments';
    const DB_USERNAME = 'ea';
    const DB_PASSWORD = 'ea-test';
}
