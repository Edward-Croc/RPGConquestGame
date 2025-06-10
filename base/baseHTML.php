<?php

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect the user to the login page if not logged in
    header(sprintf('Location: /%s/connection/loginForm.php', $_SESSION['FOLDER']));
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
        if ($pageName !== 'accueil') echo sprintf('<a href="/%s/base/accueil.php">Accueil</a>', $_SESSION['FOLDER']); 
        if ($pageName !== 'view_workers') echo sprintf('<a href="/%s/workers/viewAll.php">Agents</a>', $_SESSION['FOLDER']);
        if ($pageName !== 'zones_action') echo sprintf('<a href="/%s/zones/action.php">Zones</a>', $_SESSION['FOLDER']);
        if ($pageName !== 'controllers_action') echo sprintf('<a href="/%s/controllers/action.php">Controllers</a>', $_SESSION['FOLDER']);
        if ($pageName !== 'systemPresentation') echo sprintf('<a href="/%s/base/systemPresentation.php">Game System</a>', $_SESSION['FOLDER']);
        if ($_SESSION['is_privileged'] == true){
            echo sprintf ('<a href="/%s/mechanics/endTurn.php" class="topbar-btn">%s</a>', $_SESSION['FOLDER'], ($mechanics['gamestate'] == 0) ? 'Start Game' : 'End Turn' );
            if ($pageName !== 'admin') {
                echo sprintf ('<a href="/%s/base/admin.php" class="topbar-btn">Configuration</a>', $_SESSION['FOLDER']);
            }
        } echo sprintf ('<a href="/%s/connection/logout.php" class="logout-btn">Logout</a>', $_SESSION['FOLDER']);
        ?>
    </div>
    <!-- Sidebar Toggle Button -->
    <?php
        echo '<span class="openbtn" onclick="toggleSidebar()"> ☰ </span>';

    require_once '../base/baseScript.php';
?>
