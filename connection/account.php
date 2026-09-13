<?php

$pageName = 'account';

require_once '../base/basePHP.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header(sprintf('Location: /%s/connection/loginForm.php', $_SESSION['FOLDER']));
    exit();
}

// $GLOBALS['DEBUG_LOG_SECTIONS'][] = 'account_page';  // uncomment to log DEBUG events from this page

$prefix = $_SESSION['GAME_PREFIX'];
$message = '';
$messageColor = 'red';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    try {
        $stmt = $gameReady->prepare("SELECT passwd FROM {$prefix}players WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        game_error_log('account_page', 'SELECT players failed : ' . $e->getMessage(), ['user_id' => $_SESSION['user_id']], 'error');
        $row = false;
    }

    if ($row === false) {
        $message = "Changement impossible : compte introuvable.";
    } elseif (!verifyPlayerPassword($current, $row['passwd'])) {
        game_error_log('account_page', 'Wrong current password', ['user_id' => $_SESSION['user_id']], 'warning');
        $message = "Mot de passe actuel incorrect.";
    } elseif (strlen($new) < 4) {
        $message = "Le nouveau mot de passe doit faire au moins 4 caractères.";
    } elseif ($new !== $confirm) {
        $message = "Les deux saisies ne correspondent pas.";
    } else {
        try {
            $stmt = $gameReady->prepare("UPDATE {$prefix}players SET passwd = :passwd WHERE id = :id");
            $stmt->execute([':passwd' => hashPlayerPassword($new), ':id' => $_SESSION['user_id']]);
            $message = "Mot de passe changé.";
            $messageColor = 'green';
        } catch (PDOException $e) {
            game_error_log('account_page', 'UPDATE players passwd failed : ' . $e->getMessage(), ['user_id' => $_SESSION['user_id']], 'error');
            $message = "Changement impossible : erreur en base.";
        }
    }
}

// Secret factions are shown here : they belong to the player looking at the page.
$playerControllers = getControllers($gameReady, (int) $_SESSION['user_id'], null, false) ?: array();

require_once '../base/baseHTML.php';
?>

<div class="content">
    <h1>Mon compte</h1>
    <p>Connecté en tant que <strong><?= htmlspecialchars((string) $_SESSION['username']) ?></strong>.</p>

    <h2>Mes factions</h2>
    <?php if (empty($playerControllers)): ?>
        <p>Aucune faction ne vous est rattachée.</p>
    <?php else: ?>
        <ul id="playerFactions">
            <?php foreach ($playerControllers as $playerController): ?>
                <li>
                    <a href="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/base/accueil.php?controller_id=<?= (int) $playerController['id'] ?>">
                        <?= htmlspecialchars(sprintf('%s %s des %s', $playerController['firstname'], $playerController['lastname'], $playerController['faction_name'])) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Changer mon mot de passe</h2>
    <?php if ($message !== ''): ?>
        <p style="color: <?= htmlspecialchars($messageColor) ?>;"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <form method="post">
        <div class="field">
            <label for="current_password">Mot de passe actuel :</label>
            <input type="password" name="current_password" id="current_password" required />
        </div>
        <div class="field">
            <label for="new_password">Nouveau mot de passe :</label>
            <input type="password" name="new_password" id="new_password" required minlength="4" />
        </div>
        <div class="field">
            <label for="confirm_password">Confirmer :</label>
            <input type="password" name="confirm_password" id="confirm_password" required minlength="4" />
        </div>
        <button type="submit" class="button is-link">Changer</button>
    </form>
</div>
</body>
</html>
