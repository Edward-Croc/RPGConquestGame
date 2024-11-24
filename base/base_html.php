<?php

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect the user to the login page if not logged in
    header('Location: '.'/RPGConquestGame/connection/login_form.php');
    exit();
}
?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPGConquestGame</title>
    <style>
        <?php include_once '../base/style.css'; ?>
    </style>
</head>
<body>
<div class="header">
    <?php echo "<h1> $pageTitle </h1>";
        if ( isset($_SESSION['controler']) )
        echo $_SESSION['controler']['firstname']. " ". $_SESSION['controler']['lastname'];
    ?>
    <div class="menu_top_left">
        <?php
            echo '<div id="endTurnCounter">
                <!-- This is where the current end turn count will be displayed -->
            </div>';
            if ($_SESSION['is_privileged'] == true){
                echo '<div>';
                if ($mecanics['gamestat'] == 0) {
                    echo '<button id="endTurnButton" class="topbar-btn">Start Game</a>';
                }else{
                    echo '<button id="endTurnButton" class="topbar-btn">End Turn</a>';
                }
                if ($pageName !== 'admin') {
                    echo '<a href="/RPGConquestGame/connection/admin.php" class="topbar-btn">Configuration</a>';
                }else{
                    echo '<a href="/RPGConquestGame/index.php" class="topbar-btn">Retour</a>';}
                echo'</div>';
            }
            
        ?>
        <a href="/RPGConquestGame/connection/logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<?php
    require_once '../base/base_script.php';
?>
