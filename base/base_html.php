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
        <?php echo sprintf('
            <div>
                <h1> %s </h1>
                <div id="endTurnCounter">
                    <!-- This is where the current end turn count will be displayed -->
                    Semaine %s
                </div>
            </div>',
            $gameTitle,
            $mecanics['turncounter']
        );
        if ( isset($_SESSION['controler']) )
        echo sprintf ("%s %s (%s)<br /> %s ", $_SESSION['controler']['firstname'], $_SESSION['controler']['lastname'], $_SESSION['controler']['id'], $_SESSION['controler']['faction_name']);
        ?>
        <div class="menu_top_left">
            <?php
                echo'<div>';
                if ($_SESSION['is_privileged'] == true){
                    /*if ($mecanics['gamestat'] == 0) {
                       echo '<button id="endTurnButton" class="topbar-btn">Start Game</button>';
                    }else{
                        echo '<button id="endTurnButton" class="topbar-btn">End Turn</button>';
                    }*/
                    echo sprintf ('<a href="/RPGConquestGame/mecanics/end_turn.php" class="topbar-btn">%s</a>', ($mecanics['gamestat'] == 0) ? 'Start Game' : 'End Turn' );
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
    </div>
<?php
    require_once '../base/base_script.php';
?>
