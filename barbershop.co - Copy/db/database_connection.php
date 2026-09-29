<?php

/*
|--------------------------------------------------------------------------
| BARBERSHOP.CO DATABASE CONNECTION
|--------------------------------------------------------------------------
| This file creates the connection between our PHP website
| and the MySQL database (supports both Localhost and Railway Cloud).
|--------------------------------------------------------------------------
*/

// Fetch connection settings from Environment Variables (Railway) 
// or fallback to default values (Localhost)
$databaseServer   = getenv('DB_HOST')     ?: (getenv('MYSQLHOST')     ?: 'localhost');
$databaseUsername = getenv('DB_USER')     ?: (getenv('MYSQLUSER')     ?: 'root');
$databasePassword = getenv('DB_PASS')     ?: (getenv('MYSQLPASSWORD') ?: '');
$databaseName     = getenv('DB_NAME')     ?: (getenv('MYSQLDATABASE') ?: 'barbershop_database');
$databasePort     = getenv('DB_PORT')     ?: (getenv('MYSQLPORT')     ?: 3306);

// Create MySQL connection with port support
$databaseConnection = mysqli_connect(
    $databaseServer,
    $databaseUsername,
    $databasePassword,
    $databaseName,
    (int)$databasePort
);

// Check if the connection was successful
if (!$databaseConnection) {
    die(
        "Database connection failed: " 
        . mysqli_connect_error()
    );
}

// Set character encoding
mysqli_set_charset(
    $databaseConnection,
    "utf8mb4"
);

?>