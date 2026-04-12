<?php
$_db_host = "localhost";
$_db_name = "skillsharehub";
$_db_user = "root";
$_db_pass = "";
$_db_charset = "utf8mb4";

$_db_dsn = "mysql:host=$_db_host;dbname=$_db_name;charset=$_db_charset";
$_db_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($_db_dsn, $_db_user, $_db_pass, $_db_options);
} catch (PDOException $e) {
    die("Database connection failed.");
}