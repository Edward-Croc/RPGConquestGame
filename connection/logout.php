<?php
session_start(); // Start the session

$folder = $_SESSION['FOLDER'];

// Unset session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect the user to the login page or any other desired page
header(sprintf('Location: /%s/base/accueil.php', $folder));
exit();

?>