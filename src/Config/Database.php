<?php
// src/Config/Database.php

namespace App\Config;

/**
 * Mock Database Class
 * For this project, we are focusing on frontend and basic functions,
 * so we are simulating the database connection to avoid setup overhead.
 */
class Database {
    private static $instance = null;

    private function __construct() {
        // No real connection needed for this prototype
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        // Return the instance itself as a dummy connection object
        return self::$instance;
    }
}
