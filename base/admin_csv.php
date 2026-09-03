<?php

$pageName = 'admin_csv';

require_once '../base/basePHP.php';

if (empty($_SESSION['is_privileged'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

$csvDir = realpath(__DIR__ . '/../var/csv');
$sectionMapPath = __DIR__ . '/../docs/config_section_map.json';
$action_msg = '';
$folder = $_SESSION['FOLDER'];

/**
 * Resolve a requested CSV filename to an absolute path inside $csvDir.
 * Rejects path traversal, missing files, and non-.csv names.
 */
function admin_csv_resolve_path(?string $csvDir, string $requested): ?string
{
    if ($csvDir === false || $csvDir === null || $csvDir === '') {
        return null;
    }
    $safe = basename($requested);
    if ($safe === '' || !str_ends_with(strtolower($safe), '.csv')) {
        return null;
    }
    if (!preg_match('/^setup[A-Za-z0-9_]+\\.csv$/', $safe)) {
        return null;
    }
    $full = $csvDir . DIRECTORY_SEPARATOR . $safe;
    $real = realpath($full);
    if ($real === false || !is_file($real)) {
        return null;
    }
    // Ensure the resolved path stays under the CSV directory.
    if (!str_starts_with($real, $csvDir . DIRECTORY_SEPARATOR) && $real !== $csvDir) {
        return null;
    }
    return $real;
}

/**
 * Known setup CSV table suffixes, longest first so
 * ressources_config wins over config.
 *
 * @return list<string>
 */
function admin_csv_known_tables(): array
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }
    $tables = [
        'ressources_config',
        'controller_ressources',
        'player_controller',
        'power_types',
        'worker_origins',
        'worker_names',
        'faction_powers',
        'transformations',
        'disciplines',
        'locations',
        'artefacts',
        'controllers',
        'factions',
        'players',
        'advanced',
        'hobbys',
        'zones',
        'jobs',
        'config',
    ];
    return $tables;
}

/**
 * Parse "setup{Scenario}_{table}.csv" into scenario + table.
 *
 * @return array{scenario: string, table: string}|null
 */
function admin_csv_parse_filename(string $filename): ?array
{
    if (!str_starts_with($filename, 'setup') || !str_ends_with(strtolower($filename), '.csv')) {
        return null;
    }
    $stem = substr($filename, 5, -4); // drop setup + .csv
    foreach (admin_csv_known_tables() as $table) {
        $suffix = '_' . $table;
        if (str_ends_with($stem, $suffix)) {
            $scenario = substr($stem, 0, -strlen($suffix));
            if ($scenario === '') {
                return null;
            }
            return ['scenario' => $scenario, 'table' => $table];
        }
    }
    return null;
}

/**
 * Read the CSV header row (first line) as an array of column names.
 *
 * @return array{ok: bool, header: list<string>, error: string|null}
 */
function admin_csv_read_header(string $fullPath): array
{
    $fh = fopen($fullPath, 'rb');
    if ($fh === false) {
        return ['ok' => false, 'header' => [], 'error' => 'Impossible d\'ouvrir le fichier.'];
    }
    $row = fgetcsv($fh);
    fclose($fh);
    if ($row === false || $row === [null] || $row === []) {
        return ['ok' => false, 'header' => [], 'error' => 'En-tête CSV vide ou illisible.'];
    }
    $header = array_map(static fn ($c) => trim((string) $c), $row);
    return ['ok' => true, 'header' => $header, 'error' => null];
}

/**
 * Collect config key names from a name/value/description CSV.
 *
 * @return list<string>
 */
function admin_csv_config_keys(string $fullPath): array
{
    $fh = fopen($fullPath, 'rb');
    if ($fh === false) {
        return [];
    }
    $header = fgetcsv($fh);
    if ($header === false) {
        fclose($fh);
        return [];
    }
    $header = array_map(static fn ($c) => trim((string) $c), $header);
    $nameIdx = array_search('name', $header, true);
    if ($nameIdx === false) {
        fclose($fh);
        return [];
    }
    $keys = [];
    while (($row = fgetcsv($fh)) !== false) {
        if (!isset($row[$nameIdx])) {
            continue;
        }
        $name = trim((string) $row[$nameIdx]);
        if ($name !== '') {
            $keys[] = $name;
        }
    }
    fclose($fh);
    return $keys;
}

$expectedConfigHeader = ['name', 'value', 'description'];

// Expected columns for common setup tables (mirrors BDD/db_connector.php $fileNames).
$expectedHeadersByTable = [
    'config' => $expectedConfigHeader,
    'ressources_config' => [
        'ressource_name', 'presentation', 'stored_text', 'is_rollable', 'is_stored',
        'base_building_cost', 'base_moving_cost', 'location_repaire_cost', 'gain_rules', 'hide_when_zero',
    ],
];

$sectionMap = null;
if (is_file($sectionMapPath)) {
    $decoded = json_decode((string) file_get_contents($sectionMapPath), true);
    if (is_array($decoded) && isset($decoded['sections']) && is_array($decoded['sections'])) {
        $sectionMap = $decoded;
        if (isset($decoded['_meta']['config_csv_header']) && is_array($decoded['_meta']['config_csv_header'])) {
            $expectedConfigHeader = array_values($decoded['_meta']['config_csv_header']);
            $expectedHeadersByTable['config'] = $expectedConfigHeader;
        }
    }
}

// GET download — stream then exit before HTML
if (isset($_GET['download'])) {
    $full = admin_csv_resolve_path($csvDir, (string) $_GET['download']);
    if ($full === null) {
        game_error_log('admin_csv_page', 'Invalid CSV download request', ['requested' => $_GET['download']], 'warning');
        http_response_code(404);
        exit('CSV file not found.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($full) . '"');
    header('Content-Length: ' . filesize($full));
    readfile($full);
    exit;
}

$filterSection = isset($_GET['section_key']) ? trim((string) $_GET['section_key']) : '';
$checkFile = isset($_GET['check']) ? basename((string) $_GET['check']) : '';
$checkResult = null;

if ($checkFile !== '') {
    $full = admin_csv_resolve_path($csvDir, $checkFile);
    if ($full === null) {
        $action_msg = "<p style='color: red;'>Fichier introuvable pour la vérification.</p>";
    } else {
        $parsed = admin_csv_parse_filename(basename($full));
        $headerInfo = admin_csv_read_header($full);
        $table = $parsed['table'] ?? '';
        $expected = $expectedHeadersByTable[$table] ?? null;
        $headerOk = $headerInfo['ok'];
        $headerMatch = $expected !== null && $headerOk && $headerInfo['header'] === $expected;
        $headerWarn = $expected !== null && $headerOk && !$headerMatch;

        $presentKeys = [];
        $sectionKeys = [];
        $missing = [];
        $extraInSection = [];
        if ($table === 'config' && $headerOk) {
            $presentKeys = admin_csv_config_keys($full);
            $presentSet = array_fill_keys($presentKeys, true);
            if ($filterSection !== '' && $sectionMap !== null && isset($sectionMap['sections'][$filterSection]['keys'])) {
                $sectionKeys = $sectionMap['sections'][$filterSection]['keys'];
                foreach ($sectionKeys as $k) {
                    if (!isset($presentSet[$k])) {
                        $missing[] = $k;
                    }
                }
                foreach ($presentKeys as $k) {
                    if (in_array($k, $sectionKeys, true)) {
                        $extraInSection[] = $k; // present ∩ section
                    }
                }
            }
        }

        $checkResult = [
            'file' => basename($full),
            'scenario' => $parsed['scenario'] ?? '?',
            'table' => $table,
            'header' => $headerInfo['header'],
            'header_error' => $headerInfo['error'],
            'expected_header' => $expected,
            'header_ok' => $headerOk && ($expected === null || $headerMatch),
            'header_warn' => $headerWarn,
            'present_keys' => $presentKeys,
            'section_key' => $filterSection,
            'section_keys' => $sectionKeys,
            'present_in_section' => $extraInSection,
            'missing_in_section' => $missing,
            'size' => filesize($full),
        ];
    }
}

// Collect CSV files grouped by scenario
$scenarios = [];
if ($csvDir !== false && is_dir($csvDir)) {
    foreach (glob($csvDir . '/setup*.csv') ?: [] as $file) {
        $name = basename($file);
        $parsed = admin_csv_parse_filename($name);
        if ($parsed === null) {
            continue;
        }
        $scenario = $parsed['scenario'];
        if (!isset($scenarios[$scenario])) {
            $scenarios[$scenario] = [];
        }
        $scenarios[$scenario][] = [
            'name' => $name,
            'table' => $parsed['table'],
            'size' => filesize($file),
            'mtime' => filemtime($file),
        ];
    }
    ksort($scenarios);
    foreach ($scenarios as &$files) {
        usort($files, static fn ($a, $b) => strcmp($a['table'], $b['table']));
    }
    unset($files);
}

$scenarioLabels = [
    'TestConfig' => 'TestConfig (tests)',
    'Japon1555CSV' => 'Shikoku (四国) 1555',
    'Vampire1966CSV' => 'Firenze Vampire 1966',
];
if ($sectionMap !== null && isset($sectionMap['_meta']['scenarios']) && is_array($sectionMap['_meta']['scenarios'])) {
    foreach ($sectionMap['_meta']['scenarios'] as $sid => $meta) {
        if (is_array($meta) && isset($meta['label'])) {
            $scenarioLabels[$sid] = $meta['label'];
        }
    }
}

require_once '../base/baseHTML.php';
?>

<div class="content">
    <h1>CSV de scénario</h1>
    <p>
        Téléchargement et vérification en lecture seule des fichiers
        <code>var/csv/setup*.csv</code>.
        Format config attendu : <code>name,value,description</code>
        (pas de colonne <code>section_key</code> — le regroupement est documentaire uniquement).
    </p>
    <p>
        Documentation :
        <a href="/<?= htmlspecialchars($folder) ?>/base/docConfig.php">Guide de configuration</a>
        —
        carte des sections :
        <code>docs/config_section_map.json</code>
    </p>
    <?= $action_msg ?>

    <form method="get" action="/<?= htmlspecialchars($folder) ?>/base/admin_csv.php" class="box" style="margin-bottom: 1.5em;">
        <h2 class="title is-5">Vérifier un CSV config par section_key</h2>
        <div class="field is-grouped is-grouped-multiline">
            <div class="control">
                <label class="label">Fichier</label>
                <div class="select">
                    <select name="check">
                        <option value="">— choisir —</option>
                        <?php foreach ($scenarios as $scenario => $files): ?>
                            <?php foreach ($files as $f): ?>
                                <?php if ($f['table'] !== 'config') {
                                    continue;
                                } ?>
                                <option value="<?= htmlspecialchars($f['name']) ?>" <?= $checkFile === $f['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="control">
                <label class="label">section_key</label>
                <div class="select">
                    <select name="section_key">
                        <option value="">— toutes / en-tête seulement —</option>
                        <?php if ($sectionMap !== null): ?>
                            <?php foreach ($sectionMap['sections'] as $sk => $sec): ?>
                                <option value="<?= htmlspecialchars($sk) ?>" <?= $filterSection === $sk ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sk) ?> — <?= htmlspecialchars($sec['title'] ?? $sk) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="control" style="align-self: flex-end;">
                <button type="submit" class="button is-link">Vérifier</button>
            </div>
        </div>
    </form>

    <?php if ($checkResult !== null): ?>
        <div class="box">
            <h2 class="title is-5">Résultat : <?= htmlspecialchars($checkResult['file']) ?></h2>
            <p>
                Scénario <strong><?= htmlspecialchars($checkResult['scenario']) ?></strong>
                — table <code><?= htmlspecialchars($checkResult['table']) ?></code>
                — <?= number_format($checkResult['size'] / 1024, 1) ?> KB
                —
                <a class="button is-small" href="/<?= htmlspecialchars($folder) ?>/base/admin_csv.php?download=<?= urlencode($checkResult['file']) ?>">Download</a>
            </p>
            <?php if ($checkResult['header_error']): ?>
                <p style="color: red;"><?= htmlspecialchars($checkResult['header_error']) ?></p>
            <?php else: ?>
                <p>
                    En-tête détecté :
                    <code><?= htmlspecialchars(implode(',', $checkResult['header'])) ?></code>
                </p>
                <?php if ($checkResult['expected_header'] !== null): ?>
                    <p>
                        En-tête attendu :
                        <code><?= htmlspecialchars(implode(',', $checkResult['expected_header'])) ?></code>
                        <?php if ($checkResult['header_ok']): ?>
                            <span style="color: #27ae60;"> — OK</span>
                        <?php elseif ($checkResult['header_warn']): ?>
                            <span style="color: #c0392b;"> — ne correspond pas</span>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p><em>Pas de schéma d'en-tête strict pour cette table (liste / download uniquement).</em></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($checkResult['table'] === 'config' && $checkResult['section_key'] !== ''): ?>
                <h3 class="title is-6">Section <code><?= htmlspecialchars($checkResult['section_key']) ?></code></h3>
                <p>
                    Présentes dans le CSV :
                    <strong><?= count($checkResult['present_in_section']) ?></strong>
                    /
                    <?= count($checkResult['section_keys']) ?>
                    clés de la section.
                </p>
                <?php if (!empty($checkResult['present_in_section'])): ?>
                    <p><strong>Trouvées :</strong>
                        <code><?= htmlspecialchars(implode(', ', $checkResult['present_in_section'])) ?></code>
                    </p>
                <?php endif; ?>
                <?php if (!empty($checkResult['missing_in_section'])): ?>
                    <p style="color: #b9770e;"><strong>Absentes du CSV</strong> (souvent OK : valeurs par défaut dans <code>minimalData.sql</code>) :
                        <code><?= htmlspecialchars(implode(', ', $checkResult['missing_in_section'])) ?></code>
                    </p>
                <?php else: ?>
                    <p style="color: #27ae60;">Toutes les clés documentées de cette section sont présentes dans ce CSV.</p>
                <?php endif; ?>
            <?php elseif ($checkResult['table'] === 'config'): ?>
                <p>Clés dans le fichier : <strong><?= count($checkResult['present_keys']) ?></strong>
                    <?php if (!empty($checkResult['present_keys'])): ?>
                        — <code><?= htmlspecialchars(implode(', ', $checkResult['present_keys'])) ?></code>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($scenarios)): ?>
        <p><em>Aucun fichier <code>setup*.csv</code> trouvé dans <code>var/csv</code>.</em></p>
    <?php else: ?>
        <?php foreach ($scenarios as $scenario => $files): ?>
            <div class="box">
                <h2 class="title is-5"><?= htmlspecialchars($scenarioLabels[$scenario] ?? $scenario) ?>
                    <span class="tag is-light"><?= htmlspecialchars($scenario) ?></span>
                </h2>
                <table class="table is-striped is-fullwidth is-size-7">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Fichier</th>
                            <th>Taille</th>
                            <th>Modifié</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $f): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($f['table']) ?></code></td>
                                <td><?= htmlspecialchars($f['name']) ?></td>
                                <td><?= number_format($f['size'] / 1024, 1) ?> KB</td>
                                <td><?= date('Y-m-d H:i', $f['mtime']) ?></td>
                                <td>
                                    <a class="button is-small" href="/<?= htmlspecialchars($folder) ?>/base/admin_csv.php?download=<?= urlencode($f['name']) ?>">Download</a>
                                    <?php if ($f['table'] === 'config'): ?>
                                        <a class="button is-small is-info is-light" href="/<?= htmlspecialchars($folder) ?>/base/admin_csv.php?check=<?= urlencode($f['name']) ?>">Check</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p style="margin-top: 1em;">
        <a href="/<?= htmlspecialchars($folder) ?>/base/admin.php">&larr; Back to admin</a>
        —
        <a href="/<?= htmlspecialchars($folder) ?>/base/configuration.php">Configuration live</a>
    </p>
</div>
