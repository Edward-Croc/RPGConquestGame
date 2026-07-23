<?php
// Include-only page — block direct HTTP access.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

/**
 * gets the comparaison table between the workers on search/investigate and there possible targets locations
 *
 * @param PDO $pdo : database connection
 * @param int|null $turn_number : turn number to filter searchers on
 * @param int|null $searcher_id : optional single searcher to restrict the comparison to
 *
 * @return array : list of (searcher, found_location) comparison rows
 */
function getLocationSearcherComparisons(PDO $pdo, int|null $turn_number = NULL, int|null $searcher_id = NULL): array {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with turn_number : ' . $turn_number, ['searcher_id' => $searcher_id], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    $investigate_actions = getValidatedInvestigateActionsForSql($pdo);
    // Whitelisted (asc|desc) — getInvestigateOrder enforces the only safe interpolation surface
    $order = strtoupper(getInvestigateOrder($pdo));
    $sql = sprintf(
        "WITH searchers AS (
            SELECT
                wa.worker_id AS searcher_id,
                wa.controller_id AS searcher_controller_id,
                wa.enquete_val AS searcher_enquete_val,
                wa.zone_id
            FROM
                {$prefix}worker_actions wa
            WHERE
                wa.action_choice IN (%s)
                AND turn_number = :turn_number
        )
        SELECT
            s.searcher_id,
            s.searcher_enquete_val,
            s.searcher_controller_id,
            z.id AS zone_id,
            z.name AS zone_name,
            CONCAT(zcc.firstname, ' ', zcc.lastname) AS zone_claimer_controller_name,
            zhc.id AS zone_holder_controller_id,
            CONCAT(zhc.firstname, ' ', zhc.lastname) AS zone_holder_controller_name,
            (s.searcher_enquete_val - z.calculated_defence_val) AS zone_discovery_diff,
            l.id AS found_id,
            l.discovery_diff AS found_discovery_diff,
            l.name AS found_name,
            l.description AS found_description,
            l.hidden_description AS found_hidden_description,
            l.can_be_destroyed AS found_can_be_destroyed,
            l.controller_id AS location_controller,
            CONCAT(lc.firstname, ' ', lc.lastname) AS location_controller_name,
            (s.searcher_enquete_val - l.discovery_diff) AS enquete_difference
        FROM searchers s
        JOIN {$prefix}zones z ON z.id = s.zone_id
        LEFT JOIN {$prefix}controllers zcc ON z.claimer_controller_id = zcc.id
        LEFT JOIN {$prefix}controllers zhc ON z.holder_controller_id = zhc.id
        JOIN {$prefix}locations l ON s.zone_id = l.zone_id
        LEFT JOIN {$prefix}controllers lc ON l.controller_id = lc.id
        %s
        ORDER BY s.searcher_enquete_val $order, s.searcher_id ASC, l.discovery_diff DESC, l.id DESC;",
        $investigate_actions,
        (!empty($searcher_id)) ? " WHERE s.searcher_id = :searcher_id" : ''
    );
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        if (!empty($searcher_id)) $stmt->bindParam(':searcher_id', $searcher_id, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        game_error_log(__FUNCTION__, 'SELECT searchers/locations comparisons Failed: ' . $e->getMessage(), ['turn_number' => $turn_number, 'searcher_id' => $searcher_id], 'error');
        return [];
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Build the per-row HTML chunk for one (searcher, found_location) pair: variant-aware (no CKL / still here / secret newly revealed).
 * The artefact list is appended VISIBLE (outside the fold) when current investigation reaches LOCATIONARTEFACTSDIFF and artefacts exist.
 * Returns [reportElement, foundSecretFlag] — the caller passes foundSecretFlag to addLocationToCKL when current reached INFORMATIONDIFF.
 *
 * @param PDO $pdo : database connection
 * @param array $row : one getLocationSearcherComparisons row
 * @param array|null $prevCkl : CKL row read before this upsert (NULL = no prior entry)
 * @param array $txtBag : pre-loaded text-config bag (slab templates, variant templates, diff thresholds)
 *
 * @return array : [reportElement HTML string, foundSecretFlag bool]
 */
function buildLocationSearchReportLine(PDO $pdo, array $row, array|null $prevCkl, array $txtBag): array {
    // $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;  // uncomment to log DEBUG events from this function
    game_error_log(__FUNCTION__, 'START with found_id : ' . $row['found_id'], ['row' => $row, 'prevCkl' => $prevCkl, 'txtBag' => $txtBag], 'debug');

    $prefix = $_SESSION['GAME_PREFIX'];
    $NAMEDIFF      = $txtBag['LOCATIONNAMEDIFF'];
    $INFODIFF      = $txtBag['LOCATIONINFORMATIONDIFF'];
    $ARTEFACTSDIFF = $txtBag['LOCATIONARTEFACTSDIFF'];
    $enqDiff = (int) $row['enquete_difference'];

    if      ($enqDiff >= $ARTEFACTSDIFF) $currentLevel = 2;
    elseif  ($enqDiff >= $INFODIFF)      $currentLevel = 1;
    elseif  ($enqDiff >= $NAMEDIFF)      $currentLevel = 0;
    else    return ['', false];

    $foundName = $row['found_name'];
    $hasSecretText = !empty($row['found_hidden_description']);
    $foundSecretFlag = ($currentLevel >= 2) && $hasSecretText;

    if ($currentLevel >= 1) {
        $descTpl = $txtBag['locationDescText'][array_rand($txtBag['locationDescText'])];
        $descbody = sprintf($descTpl, $foundName, $row['found_description']);
        if ($foundSecretFlag) {
            $descbody .= "<br />" . $row['found_hidden_description'];
        }
        if ((int) $row['found_can_be_destroyed'] == 1) {
            $descbody .= $txtBag['locationDestroyableText'][array_rand($txtBag['locationDestroyableText'])];
        }
    } else {
        $nameTpl = $txtBag['locationNameText'][array_rand($txtBag['locationNameText'])];
        $descbody = sprintf($nameTpl, $foundName);
    }

    $artefactsHtml = '';
    if ($currentLevel >= 2) {
        $stmtArt = $pdo->prepare("SELECT name, description FROM {$prefix}artefacts WHERE location_id = :location_id");
        $stmtArt->execute([':location_id' => $row['found_id']]);
        $artefacts = $stmtArt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($artefacts)) {
            $artefactsHtml = "<p>Ce lieu contient : <ul>";
            foreach ($artefacts as $art) {
                $artefactsHtml .= sprintf(
                    "<li><strong>%s</strong>: %s</li>",
                    htmlspecialchars($art['name']),
                    htmlspecialchars($art['description'])
                );
            }
            $artefactsHtml .= "</ul></p>";
        }
    }

    if (empty($prevCkl)) {
        $coreElement = "<p>" . $descbody . "</p>";
    } else {
        $prevSecret = !empty($prevCkl['found_secret']);
        $newSecret = (!$prevSecret) && $foundSecretFlag;
        if ($newSecret) {
            $coreElement = "<p>" . $descbody . "</p>";
        } else {
            $stillTpl = $txtBag['textesLocationStillHere'][array_rand($txtBag['textesLocationStillHere'])];
            $summary = sprintf($stillTpl, $foundName);
            $coreElement = '<details><summary>' . $summary . '</summary><p>' . $descbody . '</p></details>';
        }
    }

    return [$coreElement . $artefactsHtml, $foundSecretFlag];
}

/**
 * Resolves end-of-turn location-search reports for every active searcher.
 * Searcher dimension is sorted by SQL ORDER BY (config-driven asc/desc, age tiebreak), so weak-then-strong dedup gives every searcher a chance to discover something new.
 *
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics row (needs turncounter)
 *
 * @return bool : true when the loop completed
 */
function locationSearchMechanic(PDO $pdo, array $mechanics): bool {
    if (strtolower(getConfig($pdo, 'DEBUG_REPORT')) == 'true') $GLOBALS['DEBUG_LOG_SECTIONS'][] = __FUNCTION__;
    game_error_log(__FUNCTION__, 'START with turncounter : ' . $mechanics['turncounter'], ['mechanics' => $mechanics], 'debug');

    echo '<div><h3>locationSearchMechanic :</h3>';
    $turn_number = $mechanics['turncounter'];
    echo "turn_number : $turn_number <br>";

    $locationsInvestigation = getLocationSearcherComparisons($pdo, $turn_number);
    game_error_log(__FUNCTION__, 'locationsInvestigation loaded', ['locationsInvestigation' => $locationsInvestigation], 'debug');

    $LOCATIONNAMEDIFF        = (int) getConfig($pdo, 'LOCATIONNAMEDIFF');
    $LOCATIONINFORMATIONDIFF = (int) getConfig($pdo, 'LOCATIONINFORMATIONDIFF');
    $LOCATIONARTEFACTSDIFF   = (int) getConfig($pdo, 'LOCATIONARTEFACTSDIFF');

    $txtBag = [
        'LOCATIONNAMEDIFF'            => $LOCATIONNAMEDIFF,
        'LOCATIONINFORMATIONDIFF'     => $LOCATIONINFORMATIONDIFF,
        'LOCATIONARTEFACTSDIFF'       => $LOCATIONARTEFACTSDIFF,
        'locationNameText'            => json_decode(getConfig($pdo, 'TEXT_LOCATION_DISCOVERED_NAME'), true),
        'locationDescText'            => json_decode(getConfig($pdo, 'TEXT_LOCATION_DISCOVERED_DESCRIPTION'), true),
        'locationDestroyableText'     => json_decode(getConfig($pdo, 'TEXT_LOCATION_CAN_BE_DESTROYED'), true),
        'textesLocationStillHere'     => json_decode(getConfig($pdo, 'textesLocationStillHere'), true),
    ];

    $textForZoneType = getConfig($pdo, 'textForZoneType');
    $controllerNameDenominatorOf = getConfig($pdo, 'controllerNameDenominatorOf');

    $reportArray = [];

    foreach ($locationsInvestigation as $row) {
        game_error_log(__FUNCTION__, 'processing row', ['row' => $row], 'debug');

        // First row for this searcher → emit zone preamble + holder context
        if (empty($reportArray[$row['searcher_id']])) {
            $holderTexte = '';
            if (!empty($row['zone_holder_controller_id']) && (int) $row['zone_discovery_diff'] > 0) {
                $holderTexte = sprintf(
                    " Ce %s est défendu par le réseau <strong> %s </strong>",
                    $textForZoneType,
                    $row['zone_holder_controller_id']
                );
                if ((int) $row['zone_discovery_diff'] > 2) {
                    $holderTexte .= sprintf(", les hommes de <strong>%s</strong>", $row['zone_holder_controller_name']);
                }
                $holderTexte .= ".";
            }

            $reportArray[$row['searcher_id']] = sprintf(
                "<p>Dans le %s <strong>%s</strong>. </br> %s %s </p>",
                $textForZoneType,
                $row['zone_name'],
                !empty($row['zone_claimer_controller_name'])
                    ? sprintf("Ce %s est sous la bannière %s <strong> %s </strong>. ", $textForZoneType, $controllerNameDenominatorOf, $row['zone_claimer_controller_name'])
                    : "",
                $holderTexte
            );
        }

        // Skip own locations
        if ($row['searcher_controller_id'] == $row['location_controller']) continue;

        $prevCkl = getCKLEntry($pdo, $row['searcher_controller_id'], $row['found_id']);

        list($reportElement, $foundSecretFlag) =
            buildLocationSearchReportLine($pdo, $row, $prevCkl, $txtBag);

        if ($reportElement !== '') {
            $separator = (substr($reportElement, -10) === '</details>') ? '<br />' : '';
            $reportArray[$row['searcher_id']] .= $reportElement . $separator;
        }

        // Upsert CKL only when current investigation reached at least INFORMATIONDIFF (name-only discoveries don't seed CKL)
        $enqDiff = (int) $row['enquete_difference'];
        if ($enqDiff >= $LOCATIONINFORMATIONDIFF) {
            addLocationToCKL($pdo, $row['searcher_controller_id'], $row['found_id'], $turn_number, $foundSecretFlag);
        }

        game_error_log(__FUNCTION__, 'updated reportArray for searcher_id ' . $row['searcher_id'], ['report' => $reportArray[$row['searcher_id']]], 'debug');
    }

    foreach ($reportArray as $worker_id => $report) {
        updateWorkerAction($pdo, $worker_id, $turn_number, NULL, ['secrets_report' => $report]);
    }

    game_error_log(__FUNCTION__, 'DONE with turncounter : ' . $turn_number, ['reportArray_count' => count($reportArray)], 'debug');

    echo '<p> locationSearchMechanic : DONE </p> </div>';
    return true;
}
