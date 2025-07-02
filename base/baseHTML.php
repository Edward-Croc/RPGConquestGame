<?php

// Check if the user is logged in
if (
    (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)
    && (empty($noConnection) || $noConnection == false)
) {
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
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
        $folder = $_SESSION['FOLDER'];
        $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        $isPrivileged = $_SESSION['is_privileged'] ?? false;

        if (!$isLoggedIn && !empty($noConnection)) {
            echo "<a href='/$folder/connection/loginForm.php' class='sidebar-btn'>Login</a>";
        } else {
            // Define main links
            $links = [
                'accueil' => ['label' => 'Accueil', 'path' => 'base/accueil.php'],
                'view_workers' => ['label' => 'Agents', 'path' => 'workers/viewAll.php'],
                'zones_action' => ['label' => 'Zones', 'path' => 'zones/action.php'],
                'controllers_action' => ['label' => 'Factions', 'path' => 'controllers/action.php'],
                'systemPresentation' => ['label' => 'Système', 'path' => 'base/systemPresentation.php']
            ];

            foreach ($links as $key => $info) {
                $selectedClass = ($pageName === $key) ? ' class="select"' : '';
                echo "<a href='/$folder/{$info['path']}'$selectedClass>{$info['label']}</a>";
            }

            // Privileged user section
            if ($isPrivileged) {
                $btnText = ($mechanics['gamestate'] ?? 0) == 0 ? 'Start Game' : 'End Turn';
                echo "<a href='/$folder/mechanics/endTurn.php' class='sidebar-btn'>$btnText</a>";

                $adminClass = ($pageName === 'admin') ? 'sidebar-btn select' : 'sidebar-btn';
                echo "<a href='/$folder/base/admin.php' class='$adminClass'>Configuration</a>";
            }

            // Logout button
            echo "<a href='/$folder/connection/logout.php' class='logout-btn'>Logout</a>";
        }
        ?>
    </div>
    <!-- Sidebar Toggle Button -->
    <?php
        echo '<span class="openbtn" onclick="toggleSidebar()"> ☰ </span>';

    require_once '../base/baseScript.php';
?>
