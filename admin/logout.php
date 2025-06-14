<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the admin login page, not the player login page
header("Location: login.php");
exit();
?>