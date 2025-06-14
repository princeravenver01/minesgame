<?php
// Start the session at the very beginning of the script.
session_start();

// --- Database Connection ---
// Using PDO for better flexibility and support for PostgreSQL

// Get connection details from the Supabase image
$db_host = "aws-0-us-east-1.pooler.supabase.com"; // From image
$db_port = "6543";                               // From image
$db_name = "postgres";                           // From image
$db_user = "postgres.ffzowimwogtxkxjspkdb";      // From image
$db_password = "[YOUR-PASSWORD]";               // Replace with your actual Supabase database password

// Construct the DSN (Data Source Name) for PostgreSQL using PDO
$dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name}";

$conn = null; // Initialize connection variable

try {
    // Create a new PDO instance
    $conn = new PDO($dsn, $db_user, $db_password);

    // Set PDO error mode to exception - this makes catching errors easier
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optional: Set the client encoding to UTF8
    // $conn->exec("SET NAMES 'UTF8'"); // Less common in modern drivers, connection often defaults correctly

    // Connection successful
    // echo "Connected successfully to PostgreSQL!"; // Uncomment for testing
} catch (PDOException $e) {
    // Log the error instead of echoing it directly in production
    error_log("Database Connection failed: " . $e->getMessage());

    // Display a user-friendly error message and stop execution
    // In a production environment, you might display a generic error page
    die("Connection failed: Please try again later.");
}

// --- Helper Function ---
// Adapted to use PDO prepared statements
if (!function_exists('get_setting')) {
    /**
     * Retrieves a setting value from the game_settings table.
     *
     * @param PDO|null $conn The database connection object (PDO instance).
     * @param string $setting_name The name of the setting to retrieve.
     * @return string|null The setting value, or null if the connection is invalid or the setting is not found.
     */
    function get_setting(?PDO $conn, string $setting_name): ?string {
        if (!$conn) {
            error_log("get_setting called with no database connection.");
            return null;
        }

        // Use try-catch for PDO operations within the function for robustness
        try {
            // Prepare the SQL statement
            // Positional placeholder (?) works with bindValue
            $stmt = $conn->prepare("SELECT setting_value FROM game_settings WHERE setting_name = ? LIMIT 1");

            // Bind the parameter value
            // PDO::PARAM_STR specifies the parameter is a string
            $stmt->bindValue(1, $setting_name, PDO::PARAM_STR);

            // Execute the statement
            $stmt->execute();

            // Fetch the result
            // fetch(PDO::FETCH_ASSOC) fetches the next row as an associative array
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Return the setting value if found, otherwise null
            if ($row) {
                // Optional: Close cursor to free resources, especially useful in loops
                $stmt->closeCursor();
                return $row['setting_value'];
            }

        } catch (PDOException $e) {
            // Log the error related to the query
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
