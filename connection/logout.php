<?php
session_start(); // Start the session

// Anonymous direct GET: nothing to log out from. loginForm.php sits in
// the same /connection/ directory so a relative Location header works
// without needing $_SESSION['FOLDER'] (which is unset for anon).
if (empty($_SESSION['logged_in']) || empty($_SESSION['FOLDER'])) {
    header('Location: loginForm.php');
    exit();
}

$folder = $_SESSION['FOLDER'];

// Unset session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect the user to the login page or any other desired page
header(sprintf('Location: /%s/base/accueil.php', $folder));
exit();

?>