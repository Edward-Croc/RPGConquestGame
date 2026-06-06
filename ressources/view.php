<?php
$pageName = 'ressources_view';
require_once '../base/basePHP.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['controller'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

require_once '../base/baseHTML.php';

if (getConfig($gameReady, 'ressource_management') !== 'TRUE') {
    echo '<div class="management"><div class="notification is-warning">La gestion des ressources est désactivée pour cette partie.</div></div>';
    exit();
}

$controller_id = (int)$_SESSION['controller']['id'];

// === MOCK DATA — to be replaced with live queries in commit 2 ===
$mockRessources = [
    ['id' => 1, 'name' => 'Koku', 'amount' => 1200, 'amount_stored' => 0, 'end_turn_gain' => 100, 'rules_estimate' => 1000],
    ['id' => 2, 'name' => 'Honneur', 'amount' => 45, 'amount_stored' => 0, 'end_turn_gain' => 10, 'rules_estimate' => 0],
];
$mockRules = [
    1 => [
        'before_claim' => [
            ['amount' => 100, 'text' => 'par zone revendiquée ce tour', 'count' => 2, 'total' => 200],
            ['amount' => 200, 'text' => 'par zone tenue', 'count' => 3, 'total' => 600],
        ],
        'after_claim' => [
            ['amount' => 100, 'text' => 'par temple possédé', 'count' => 1, 'total' => 100],
            ['amount' => 100, 'text' => 'par forteresse possédée', 'count' => 1, 'total' => 100],
        ],
    ],
    2 => ['before_claim' => [], 'after_claim' => []],
];
$mockVisibleFactions = [
    ['id' => 2, 'firstname' => 'Date',     'lastname' => 'Masamune',  'faction_name' => 'Tendai'],
    ['id' => 3, 'firstname' => 'Tokugawa', 'lastname' => 'Ieyasu',    'faction_name' => 'Wako'],
    ['id' => 4, 'firstname' => 'Toyotomi', 'lastname' => 'Hideyoshi', 'faction_name' => 'Shikoku'],
];
$mockReceivedGifts = [
    ['turn' => 12, 'giver' => 'Date Masamune (Tendai)',     'ressource' => 'Koku',    'amount' => 100],
    ['turn' => 10, 'giver' => 'Oda Nobunaga (Ashikaga)',    'ressource' => 'Honneur', 'amount' => 5],
];
// === END MOCK DATA ===
?>
<div class="management">
    <h1>Ressources de la faction</h1>
    <div class="content columns">

        <div class="column is-two-thirds">

            <div class="box mb-5">
                <h3 class="title is-5">Mes ressources</h3>
                <div class="table-container">
                    <table class="table is-striped is-fullwidth">
                        <thead>
                            <tr>
                                <th>Ressource</th>
                                <th class="has-text-right">Montant</th>
                                <th class="has-text-right">Stockée</th>
                                <th class="has-text-right">Estimation tour +1</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($mockRessources as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td class="has-text-right"><?= (int)$r['amount'] ?></td>
                                <td class="has-text-right"><?= (int)$r['amount_stored'] ?></td>
                                <td class="has-text-right">+<?= (int)($r['end_turn_gain'] + $r['rules_estimate']) ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box mb-5">
                <h3 class="title is-5">Règles d'obtention</h3>
<?php foreach ($mockRessources as $r):
    $rules = $mockRules[$r['id']] ?? ['before_claim' => [], 'after_claim' => []];
    $hasAny = !empty($rules['before_claim']) || !empty($rules['after_claim']);
?>
                <h4 class="title is-6 mt-4"><?= htmlspecialchars($r['name']) ?></h4>
<?php if (!$hasAny): ?>
                <p class="has-text-grey">Aucune règle conditionnelle.</p>
                <p>Gain de fin de tour fixe : +<?= (int)$r['end_turn_gain'] ?></p>
<?php else: ?>
<?php if (!empty($rules['before_claim'])): ?>
                <h5 class="title is-6 has-text-weight-semibold mt-2">Avant la résolution des conquêtes</h5>
                <ul>
<?php foreach ($rules['before_claim'] as $rule): ?>
                    <li>+<?= (int)$rule['amount'] ?> <?= htmlspecialchars($rule['text']) ?> &middot; × <?= (int)$rule['count'] ?> = <strong>+<?= (int)$rule['total'] ?></strong></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
<?php if (!empty($rules['after_claim'])): ?>
                <h5 class="title is-6 has-text-weight-semibold mt-2">Après la résolution des conquêtes</h5>
                <ul>
<?php foreach ($rules['after_claim'] as $rule): ?>
                    <li>+<?= (int)$rule['amount'] ?> <?= htmlspecialchars($rule['text']) ?> &middot; × <?= (int)$rule['count'] ?> = <strong>+<?= (int)$rule['total'] ?></strong></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
                <p class="mt-3">Gain de fin de tour fixe : +<?= (int)$r['end_turn_gain'] ?></p>
                <p>Estimation totale du tour suivant : <strong>+<?= (int)($r['end_turn_gain'] + $r['rules_estimate']) ?></strong></p>
<?php endif; ?>
<?php endforeach; ?>
            </div>

        </div>

        <div class="column">

            <div class="box mb-5">
                <h3 class="title is-5">Faire un don</h3>
                <form method="POST" action="/<?= htmlspecialchars($_SESSION['FOLDER']) ?>/ressources/action.php">
                    <div class="field">
                        <label class="label">Ressource</label>
                        <div class="control for-select">
                            <div class="select is-fullwidth">
                                <select name="ressource_id" id="giftRessourceSelect" required>
<?php foreach ($mockRessources as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" data-max="<?= (int)$r['amount'] ?>"><?= htmlspecialchars($r['name']) ?> (max <?= (int)$r['amount'] ?>)</option>
<?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Quantité</label>
                        <div class="control">
                            <input type="number" name="amount" min="1" class="input" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Faction cible</label>
<?= showControllerSelect($mockVisibleFactions, null, 'target_controller_id') ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <input type="submit" name="giftRessource" value="Donner" class="button is-link">
                        </div>
                    </div>
                </form>
            </div>

            <div class="box mb-5">
                <h3 class="title is-5">Donations reçues</h3>
<?php if (empty($mockReceivedGifts)): ?>
                <p class="has-text-grey">Aucune donation reçue.</p>
<?php else: ?>
                <ul>
<?php foreach ($mockReceivedGifts as $g): ?>
                    <li>T<?= (int)$g['turn'] ?> &mdash; <?= htmlspecialchars($g['giver']) ?> vous a donné <strong><?= (int)$g['amount'] ?> <?= htmlspecialchars($g['ressource']) ?></strong></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>

        </div>

    </div>
</div>
