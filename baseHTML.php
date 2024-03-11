

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPGConquestGame</title>
    <style>
        <?php include_once './style.css'; ?>
    </style>
</head>
<body>
<div class="header">
    <h1>RPGConquestGame</h1>
    <div class="menu_top_left">
        <?php
            if ($_SESSION['is_privileged'] == true){
                echo '<a href="admin.php" class="admin-btn">Configuration</a>';
            }
        ?>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</div>