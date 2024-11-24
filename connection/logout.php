<?php
session_start(); // Start the session

// Unset session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect the user to the login page or any other desired page
header('Location: '.'/RPGConquestGame/index.php');
exit();

?>