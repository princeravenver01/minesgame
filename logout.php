<?php
session_start(); // Start the session to access its contents

// Unset all of the session variables for the player.
// We keep this simple to avoid accidentally logging out an admin in the same browser.
unset($_SESSION['user_id']);
unset($_SESSION['username']);

// In a more robust system, you might destroy the whole session,
// but for this separated setup, unsetting is clean and effective.
// $_SESSION = array();
// session_destroy();

// Redirect the user to the player login page with a status message
header("Location: login.php?status=loggedout");
exit();
?>