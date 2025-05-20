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
    <div class="header">
        <!-- OLD HEADER
        <?php echo sprintf('
            <div>
                <h1 id="gameTitle"> %s </h1>
                <div id="endTurnCounter">
                    %s %s
                </div>
            </div>',
            $gameTitle,
            ucfirst(getConfig($gameReady, 'timeValue')),
            $mechanics['turncounter']
        );
        if ( isset($_SESSION['controller']) )
        echo sprintf ("%s %s (%s)<br /> %s ", $_SESSION['controller']['firstname'], $_SESSION['controller']['lastname'], $_SESSION['controller']['id'], $_SESSION['controller']['faction_name']);
        ?>
        <div class="menu_top_left">
            <?php
                echo'<div>';
                if ($_SESSION['is_privileged'] == true){
                    echo sprintf ('<a href="/RPGConquestGame/mechanics/end_turn.php" class="topbar-btn">%s</a>', ($mechanics['gamestat'] == 0) ? 'Start Game' : 'End Turn' );
                    if ($pageName !== 'admin') {
                        echo '<a href="/RPGConquestGame/connection/admin.php" class="topbar-btn">Configuration</a>';
                    }
                    }
                if ($pageName !== 'accueil') {
                    echo '<a href="/RPGConquestGame/index.php" class="topbar-btn">Retour</a>';
                }
                echo'</div>';

            ?>
            <a href="/RPGConquestGame/connection/logout.php" class="logout-btn">Logout</a>
        </div>
        -->
    </div>
    <!-- Sidebar MENU -->
    <div id="sidebar" class="sidebar">
        <?php echo sprintf('<div> %s %s</div>',
            ucfirst(getConfig($gameReady, 'timeValue')), $mechanics['turncounter']
            );
            if (!empty($_SESSION['controller']['firstname']))
                echo sprintf('<div> %s %s (%s) les %s </div>', $_SESSION['controller']['firstname'], $_SESSION['controller']['lastname'], $_SESSION['controller']['id'], $_SESSION['controller']['faction_name']);
        ?>
        <a href="javascript:void(0)" class="closebtn" onclick="toggleSidebar()">&times;</a>
        <?php if ($pageName !== 'accueil') echo '<a href="/RPGConquestGame/base/accueil.php">Accueil</a>'; ?>
        <a href="/RPGConquestGame/workers/action.php">Agents</a>
        <a href="/RPGConquestGame/zones/action.php">Zones</a>
        <a href="/RPGConquestGame/controllers/action.php">Controllers</a>
        <a href="/RPGConquestGame/base/system_presentation.php">Game System</a>
        <?php
            if ($_SESSION['is_privileged'] == true){
                echo sprintf ('<a href="/RPGConquestGame/mechanics/end_turn.php" class="topbar-btn">%s</a>', ($mechanics['gamestat'] == 0) ? 'Start Game' : 'End Turn' );
                if ($pageName !== 'admin') {
                    echo '<a href="/RPGConquestGame/connection/admin.php" class="topbar-btn">Configuration</a>';
                }
            }
        ?>
        <a href="/RPGConquestGame/connection/logout.php" class="logout-btn">Logout</a>
    </div>
    <!-- Sidebar Toggle Button -->
    <span class="openbtn" onclick="toggleSidebar()"> <?php echo sprintf('☰ %s',$gameTitle); ?></span>
<?php
    require_once '../base/base_script.php';
?>
