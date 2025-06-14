<?php
// Start the session at the very beginning of the script.
session_start();

// --- Database Connection ---
// Using PDO for flexibility and support for PostgreSQL

// --- Supabase Session Pooler Connection Details ---
// REPLACE '[YOUR-PASSWORD]' with your actual database password from Supabase
$db_host = "aws-0-us-east-1.pooler.supabase.com"; // From Supabase pooler config
$db_port = "5432";                               // From Supabase Session Pooler config
$db_name = "postgres";                           // From Supabase pooler config
$db_user = "postgres.ffzowimwogtxkxjspkdb";      // From Supabase pooler config
$db_password = "password1";               // <--- REPLACE THIS WITH YOUR ACTUAL PASSWORD

// Construct the DSN (Data Source Name) for PostgreSQL using PDO
$dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name}";

$conn = null; // Initialize connection variable

try {
    // Create a new PDO instance
    $conn = new PDO($dsn, $db_user, $db_password);

    // Set PDO error mode to exception - this makes catching errors easier and recommended
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optional: Set the client encoding if needed (often defaults correctly to UTF8)
    // $conn->exec("SET NAMES 'UTF8'");

    // Connection successful
    // echo "Connected successfully to PostgreSQL!"; // Uncomment for testing if needed
} catch (PDOException $e) {
    // Log the detailed error message (e.g., to a file or monitoring service)
    error_log("Database Connection failed: " . $e->getMessage());

    // In a production environment, display a generic error message to the user
    // Do not expose sensitive details from $e->getMessage() directly to the user.
    die("Connection failed: Please try again later.");
}

// --- Helper Function ---
// Adapted to use PDO prepared statements
if (!function_exists('get_setting')) {
    /**
     * Retrieves a setting value from the game_settings table using PDO.
     *
     * @param PDO|null $conn The database connection object (PDO instance).
     * @param string $setting_name The name of the setting to retrieve.
     * @return string|null The setting value, or null if the connection is invalid, the setting is not found, or an error occurs.
     */
    function get_setting(?PDO $conn, string $setting_name): ?string {
        if (!$conn) {
            error_log("get_setting called with no valid database connection.");
            return null;
        }

        try {
            // Prepare the SQL statement
            // Use positional placeholder (?)
            $stmt = $conn->prepare("SELECT setting_value FROM game_settings WHERE setting_name = ? LIMIT 1");

            // Bind the parameter value
            // 1 refers to the first placeholder (the ?)
            $stmt->bindValue(1, $setting_name, PDO::PARAM_STR);

            // Execute the statement
            $stmt->execute();

            // Fetch the result row as an associative array
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Return the setting value if found, otherwise null
            if ($row) {
                // Optional: Close cursor to free resources if fetching only one row
                $stmt->closeCursor();
                return $row['setting_value'];
            }

        } catch (PDOException $e) {
            // Log the error related to the query execution
            error_log("Error executing get_setting query: " . $e->getMessage());
            // Return null on error
            return null;
        }

        // Return null if setting not found or if an error occurred
        return null;
    }
}

// THE FIX: Removed the closing PHP tag to prevent any accidental whitespace
// from being sent before header() calls in other scripts.
