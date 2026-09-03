<?php

require_once '../base/basePHP.php'; // Set up $pdo and session

// Admin-only page: require privileged session
if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

$pageName = 'admin_combat';

// Read-only page : no POST handler, so no redirect-preserving-GET step either.
$locationAttackMode = getConfig($gameReady, 'locationAttackMode');
$agentLocationMode = ($locationAttackMode === 'agent_attack_defence');

$filterOptions = getWorkerCombatLogFilterOptions($gameReady);

// NULL sentinel, never 0 : turn 0 is a real populated turn.
$filterWorker = null;
if (isset($_GET['worker']) && $_GET['worker'] !== '' && ctype_digit((string) $_GET['worker'])) {
    $filterWorker = (int) $_GET['worker'];
}
$filterTurn = null;
if (isset($_GET['turn']) && $_GET['turn'] !== '' && ctype_digit((string) $_GET['turn'])) {
    $filterTurn = (int) $_GET['turn'];
}
// Dropped, not merely hidden, outside agent_attack_defence.
$filterLocation = null;
if (
    $agentLocationMode
    && isset($_GET['location']) && $_GET['location'] !== '' && ctype_digit((string) $_GET['location'])
) {
    $filterLocation = (int) $_GET['location'];
}
$hasFilter = ($filterWorker !== null || $filterTurn !== null || $filterLocation !== null);

$logs = getWorkerCombatLogs(
    $gameReady,
    [
        'worker_id'   => $filterWorker,
        'turn'        => $filterTurn,
        'location_id' => $filterLocation,
    ],
    'turn',
    'desc'
);

// Counted over the returned rows so the banner can never disagree with the table.
$unresolvedCount = count(array_filter($logs, fn ($row) => ($row['outcome'] ?? null) === null));

$outcomeLabels = [
    'miss'         => 'Échec',
    'kill'         => 'Mort du défenseur',
    'capture'      => 'Capture du défenseur',
    'riposte_kill' => 'Mort de l’attaquant (riposte)',
    'mutual_kill'  => 'Mort des deux agents',
];

/**
 * Render a name plus its id, falling back to a deleted-entity label so a cell is
 * never blank once an id is known.
 *
 * @param string|null $name : denormalised name, may be NULL after a hard delete
 * @param int|null $id : entity id
 * @param string $deletedLabel : label to use when the name is gone
 *
 * @return string : escaped cell content
 */
function combatLogEntityCell(string|null $name, int|null $id, string $deletedLabel): string
{
    if (empty($id)) {
        return '—';
    }
    if (empty($name)) {
        return htmlspecialchars(sprintf('%s (#%d)', $deletedLabel, $id), ENT_QUOTES);
    }
    return htmlspecialchars(sprintf('%s (#%d)', $name, $id), ENT_QUOTES);
}

/**
 * Build the select options for one combat-log filter.
 *
 * @param array $options : option rows carrying an id key and a label
 * @param string $idKey : name of the id key inside each row
 * @param int|null $current : currently selected id
 * @param string $allLabel : label of the leading no-filter option
 *
 * @return string : rendered option list
 */
function combatLogFilterOptions(array $options, string $idKey, int|null $current, string $allLabel): string
{
    $html = sprintf('<option value="">%s</option>', htmlspecialchars($allLabel, ENT_QUOTES));
    foreach ($options as $option) {
        $html .= sprintf(
            '<option value="%d"%s>%s</option>',
            (int) $option[$idKey],
            ($current === (int) $option[$idKey]) ? ' selected' : '',
            htmlspecialchars($option['label'], ENT_QUOTES)
        );
    }
    return $html;
}

require_once '../base/baseHTML.php';
?>
<div class='management'
    data-combat-log="1"
    data-location-mode="<?= htmlspecialchars((string) $locationAttackMode, ENT_QUOTES) ?>"
    data-row-count="<?= count($logs) ?>"
>
    <h1>Journal des combats d’agents</h1>

    <form method="get" class="box mb-5" data-combat-filter="1">
        <h3 class="title is-4">Filtres :</h3>
        <div class="field is-grouped is-grouped-multiline">
            <div class="control">
                <div class="select">
                    <select name="worker"><?= combatLogFilterOptions($filterOptions['workers'], 'worker_id', $filterWorker, 'Tous les agents') ?></select>
                </div>
            </div>
            <div class="control">
                <div class="select">
                    <select name="turn">
                        <option value="">Tous les tours</option>
                        <?php foreach ($filterOptions['turns'] as $turnOption): ?>
                        <option value="<?= (int) $turnOption ?>"<?= ($filterTurn === (int) $turnOption) ? ' selected' : '' ?>><?= (int) $turnOption ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($agentLocationMode): ?>
            <div class="control">
                <div class="select">
                    <select name="location"><?= combatLogFilterOptions($filterOptions['locations'], 'location_id', $filterLocation, 'Tous les lieux') ?></select>
                </div>
            </div>
            <?php endif; ?>
            <div class="control">
                <input type="submit" value="Filtrer" class="button is-link">
            </div>
            <div class="control">
                <a href="management_combat.php" class="button" data-combat-filter-reset="1">Reset</a>
            </div>
        </div>
    </form>

    <?php if ($unresolvedCount > 0): ?>
    <p class="has-text-danger" data-unresolved-count="<?= (int) $unresolvedCount ?>">
        <?= (int) $unresolvedCount ?> combat(s) non résolu(s) — démarrés sans jamais aboutir.
    </p>
    <?php else: ?>
    <p data-unresolved-count="0">Aucun combat non résolu.</p>
    <?php endif; ?>

<?php if (empty($logs)): ?>
    <?php if ($hasFilter): ?>
    <p data-combat-empty="filtered">Aucun combat pour ce filtre.</p>
    <p><a href="management_combat.php" class="button is-small is-info mt-2">Reset</a></p>
    <?php else: ?>
    <p data-combat-empty="all">Aucun combat enregistré.</p>
    <?php endif; ?>
<?php else: ?>
    <table border="1" cellpadding="5">
        <tr>
            <th>Tour</th>
            <th>Date</th>
            <th>Attaquant</th>
            <th>Défenseur</th>
            <th>Écart attaque</th>
            <th>Écart riposte</th>
            <th>Issue</th>
            <th>Zone</th>
            <?php if ($agentLocationMode): ?>
            <th>Lieu</th>
            <?php endif; ?>
        </tr>
        <?php foreach ($logs as $log): ?>
        <?php $resolved = (($log['outcome'] ?? null) !== null); ?>
        <tr
            class="combat-row<?= $resolved ? '' : ' combat-unresolved has-background-warning-light' ?>"
            data-combat-log-id="<?= (int) $log['id'] ?>"
            data-turn="<?= (int) $log['turn'] ?>"
            data-outcome="<?= htmlspecialchars((string) ($log['outcome'] ?? ''), ENT_QUOTES) ?>"
            data-resolved="<?= $resolved ? '1' : '0' ?>"
            data-attempt="<?= (int) $log['attempt'] ?>"
            data-attacker-worker-id="<?= (int) $log['attacker_id'] ?>"
            data-attacker-controller-id="<?= empty($log['attacker_controller_id']) ? '' : (int) $log['attacker_controller_id'] ?>"
            data-defender-worker-id="<?= (int) $log['defender_id'] ?>"
            data-defender-controller-id="<?= empty($log['defender_controller_id']) ? '' : (int) $log['defender_controller_id'] ?>"
            data-zone-id="<?= empty($log['zone_id']) ? '' : (int) $log['zone_id'] ?>"
            data-location-id="<?= empty($log['location_id']) ? '' : (int) $log['location_id'] ?>"
        >
            <td><?= (int) $log['turn'] ?></td>
            <td><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES) ?></td>
            <td>
                <?= combatLogEntityCell($log['attacker_name'] ?? null, empty($log['attacker_id']) ? null : (int) $log['attacker_id'], 'Agent supprimé') ?>
                <br /><i><?= combatLogEntityCell($log['attacker_controller_name'] ?? null, empty($log['attacker_controller_id']) ? null : (int) $log['attacker_controller_id'], 'Contrôleur inconnu') ?></i>
            </td>
            <td>
                <?= combatLogEntityCell($log['defender_name'] ?? null, empty($log['defender_id']) ? null : (int) $log['defender_id'], 'Agent supprimé') ?>
                <br /><i><?= combatLogEntityCell($log['defender_controller_name'] ?? null, empty($log['defender_controller_id']) ? null : (int) $log['defender_controller_id'], 'Contrôleur inconnu') ?></i>
            </td>
            <!-- A NULL diff renders as an em dash : (int)null would fabricate a 0 -->
            <td><?= ($log['attack_difference'] === null) ? '—' : (int) $log['attack_difference'] ?></td>
            <td><?= ($log['riposte_difference'] === null) ? '—' : (int) $log['riposte_difference'] ?></td>
            <td>
                <?php if (!$resolved): ?>
                ⚠ NON RÉSOLU
                <?php else: ?>
                <?= htmlspecialchars($outcomeLabels[$log['outcome']] ?? (string) $log['outcome'], ENT_QUOTES) ?>
                <?php endif; ?>
            </td>
            <td><?= combatLogEntityCell($log['zone_name'] ?? null, empty($log['zone_id']) ? null : (int) $log['zone_id'], 'Zone supprimée') ?></td>
            <?php if ($agentLocationMode): ?>
            <td><?= combatLogEntityCell($log['location_name'] ?? null, empty($log['location_id']) ? null : (int) $log['location_id'], 'Lieu supprimé') ?></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
</div>
