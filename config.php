<?php
// ============ DATABASE CONFIGURATION ============
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bus_booking_system";

// ============ CREATE CONNECTION ============
$conn = new mysqli($host, $user, $pass, $db);

// ============ CHECK CONNECTION ============
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ============ SET CHARACTER SET ============
$conn->set_charset("utf8mb4");

// ============ SET TIME ZONE ============
date_default_timezone_set('Asia/Colombo');

// ============ ERROR REPORTING ============
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============ SESSION START ============
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ LOAD TAB AUTHENTICATION FUNCTIONS ============
require_once __DIR__ . '/tab_auth.php';

// ============ FUNCTION: GET SAFE VALUE ============
if (!function_exists('getValue')) {
    function getValue($data, $key, $default = 'N/A') {
        return isset($data[$key]) && !empty($data[$key]) ? htmlspecialchars($data[$key]) : $default;
    }
}

// ============ FUNCTION: FORMAT DATE ============
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'F d, Y') {
        if (empty($date) || $date == '0000-00-00') {
            return 'N/A';
        }
        return date($format, strtotime($date));
    }
}

// ============ FUNCTION: CHECK IF USER IS LOGGED IN ============
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    }
}

// ============ FUNCTION: GENERATE REFERENCE CODE ============
if (!function_exists('generateRefCode')) {
    function generateRefCode() {
        return 'BK-' . strtoupper(substr(uniqid(), -5));
    }
}
?>