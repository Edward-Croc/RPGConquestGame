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
<body class='content'>
    <div class="header"><?php
        echo sprintf(
            '%s : %s %s <br>',
            $gameTitle,
            ucfirst(getConfig($gameReady, 'timeValue')),
            $mechanics['turncounter']
        );
        if ( isset($_SESSION['controller']) )
            echo sprintf ("  %s %s (%s) des %s ", $_SESSION['controller']['firstname'], $_SESSION['controller']['lastname'], $_SESSION['controller']['id'], $_SESSION['controller']['faction_name']);
    ?></div>
    <!-- Sidebar MENU -->
    <div id="sidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="toggleSidebar()">&times;</a>
        <?php 
        if ($pageName !== 'accueil') echo '<a href="/RPGConquestGame/base/accueil.php">Accueil</a>'; 
        if ($pageName !== 'view_workers') echo '<a href="/RPGConquestGame/workers/action.php">Agents</a>';
        if ($pageName !== 'zones_action') echo '<a href="/RPGConquestGame/zones/action.php">Zones</a>';
        if ($pageName !== 'controllers_action') echo '<a href="/RPGConquestGame/controllers/action.php">Controllers</a>';
        if ($pageName !== 'system_presentation') echo '<a href="/RPGConquestGame/base/system_presentation.php">Game System</a>';
        if ($_SESSION['is_privileged'] == true){
            echo sprintf ('<a href="/RPGConquestGame/mechanics/end_turn.php" class="topbar-btn">%s</a>', ($mechanics['gamestate'] == 0) ? 'Start Game' : 'End Turn' );
            if ($pageName !== 'admin') {
                echo '<a href="/RPGConquestGame/connection/admin.php" class="topbar-btn">Configuration</a>';
            }
        }
        ?>
        <a href="/RPGConquestGame/connection/logout.php" class="logout-btn">Logout</a>
    </div>
    <!-- Sidebar Toggle Button -->
    <?php
        echo '<span class="openbtn" onclick="toggleSidebar()"> ☰ </span>';

    require_once '../base/base_script.php';
?>
