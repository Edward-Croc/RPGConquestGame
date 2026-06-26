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

$gainEstimate = ressourceGainEstimateForController($gameReady, $controller_id);
$ressourcesList = filterVisibleRessources(getRessources($gameReady, $controller_id), $gainEstimate);
$hasStoredRessource = false;
foreach ($ressourcesList as $r) {
    if (!empty($r['is_stored'])) {
        $hasStoredRessource = true;
        break;
    }
}
$visibleFactions = getControllers($gameReady, NULL, NULL, true, $controller_id) ?: [];
$receivedGifts = getRessourceGiftsReceived($gameReady, $controller_id);
$timeValueLabel = ucfirst(getConfig($gameReady, 'timeValue') ?: 'Tour');
?>
<div class="factions">
    <h1>Ressources de la faction</h1>
<?php if (!empty($_GET['feedback']) && in_array($_GET['feedback'], ['success', 'error'], true)): ?>
    <div class="notification <?= $_GET['feedback'] === 'success' ? 'is-success' : 'is-danger' ?>">
        <?= htmlspecialchars($_GET['msg'] ?? '') ?>
    </div>
<?php endif; ?>
    <div class="content columns">

        <div class="column is-two-thirds">

            <div class="box mb-5">
                <h3 class="title is-5">Mes ressources</h3>
                <div class="table-container">
                    <table class="table is-striped is-fullwidth">
                        <thead>
                            <tr>
                                <th>Ressource</th>
                                <th class="has-text-right">Montant utilisable</th>
<?php if ($hasStoredRessource): ?>
                                <th class="has-text-right">Stockée inaccessible</th>
<?php endif; ?>
                                <th class="has-text-right">Estimation tour +1</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($ressourcesList as $r):
    $rulesTotal = $gainEstimate[(int)$r['ressource_id']]['total'] ?? 0;
?>
                            <tr>
                                <td><?= htmlspecialchars($r['ressource_name']) ?></td>
                                <td class="has-text-right"><?= (int)$r['amount'] ?></td>
                                <?php if ($hasStoredRessource): ?>
                                <td class="has-text-right"><?= (int)$r['amount_stored'] ?></td>
                                <?php endif; ?>
                                <td class="has-text-right">+<?= (int)($r['end_turn_gain'] + $rulesTotal) ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box mb-5">
                <h3 class="title is-5">Règles d'obtention</h3>
<?php foreach ($ressourcesList as $r):
    $rules = $gainEstimate[(int)$r['ressource_id']] ?? ['before_claim' => [], 'after_claim' => [], 'total' => 0];
    $hasAny = !empty($rules['before_claim']) || !empty($rules['after_claim']);
?>
                <h4 class="title is-5 has-text-weight-bold mt-5"><?= htmlspecialchars($r['ressource_name']) ?> :</h4>
<?php if (!$hasAny): ?>
                <p class="has-text-grey">Aucune règle conditionnelle.</p>
                <p>Gain de fin de tour fixe : <strong>+<?= (int)$r['end_turn_gain'] ?></strong></p>
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
                <p class="mt-3">Gain de fin de tour fixe : <strong>+<?= (int)$r['end_turn_gain'] ?></strong></p>
                <p>Estimation totale du tour suivant : <strong>+<?= (int)($r['end_turn_gain'] + ($rules['total'] ?? 0)) ?></strong></p>
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
<?php foreach ($ressourcesList as $r): ?>
                                    <option value="<?= (int)$r['ressource_id'] ?>" data-max="<?= (int)$r['amount'] ?>"><?= htmlspecialchars($r['ressource_name']) ?> (max <?= (int)$r['amount'] ?>)</option>
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
<?= showControllerSelect($visibleFactions, null, 'target_controller_id') ?>
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
<?php if (empty($receivedGifts)): ?>
                <p class="has-text-grey">Aucune donation reçue.</p>
<?php else:
    $giftsByTurn = [];
    foreach ($receivedGifts as $g) { $giftsByTurn[(int)$g['turn']][] = $g; }
    krsort($giftsByTurn);
    $first = true;
    $idx = 0;
?>
                <div class="tabs title"><ul>
<?php foreach ($giftsByTurn as $turn => $_tabRows): ?>
                    <li<?= $first ? ' class="is-active"' : '' ?> data-tab-group="ressource-gifts" data-tab-index="<?= $idx ?>"><a onclick="selectTab('ressource-gifts', <?= $idx ?>)"><?= htmlspecialchars($timeValueLabel) ?> <?= $turn ?></a></li>
<?php $first = false; $idx++; endforeach; ?>
                </ul></div>
<?php
    $first = true;
    $idx = 0;
    foreach ($giftsByTurn as $turn => $tabRows):
?>
                <div class="tab-content"<?= $first ? '' : ' style="display:none"' ?> data-tab-group="ressource-gifts" data-tab-index="<?= $idx ?>">
                    <ul>
<?php foreach ($tabRows as $g): ?>
                        <li><?= htmlspecialchars($g['giver']) ?> vous a donné <strong><?= (int)$g['amount'] ?> <?= htmlspecialchars($g['ressource']) ?></strong></li>
<?php endforeach; ?>
                    </ul>
                </div>
<?php $first = false; $idx++; endforeach; ?>
<?php endif; ?>
            </div>

        </div>

    </div>
</div>
