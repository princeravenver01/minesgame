<?php
// Start the session at the very beginning of the script.
session_start();

// --- Database Connection ---
$servername = "localhost";
$username = "root";
$password = ""; // Your XAMPP password, usually empty by default
$dbname = "mines_game_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  // Log the error instead of die() in production for cleaner output, but die is fine for dev
  error_log("Database Connection failed: " . $conn->connect_error);
  // Die here to prevent other scripts from trying to use a bad connection
  die("Connection failed: " . $conn_>connect_error);
}

// --- Helper Function ---
// Added null check for robustness, though mysqli::prepare should return false on error
if (!function_exists('get_setting')) {
    function get_setting($conn, $setting_name) {
        if (!$conn) {
            error_log("get_setting called with no database connection.");
            return null;
        }
        $stmt = $conn->prepare("SELECT setting_value FROM game_settings WHERE setting_name = ? LIMIT 1"); // Added LIMIT 1
        if (!$stmt) {
            error_log("Prepare failed for get_setting: " . $conn->error);
            return null;
        }
        $stmt->bind_param("s", $setting_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close(); // Close statement as soon as result is fetched
            return $row['setting_value'];
        }
        $stmt->close();
        return null; // Return null if setting not found
    }
}

// THE FIX: Removed the closing PHP tag to prevent any accidental whitespace
// from being sent before header() calls in other scripts.