<?php
// Include-only page — block direct HTTP access.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

/**
 *  Remove curly braces and quotes and Split the string by commas into an array
 * 
 * @param string $input
 * 
 * @return array 
 * 
 */
function cleanAndSplitString($input) {
    // Remove curly braces and quotes
    $cleaned = str_replace(['{', '}', '"'], '', $input);
    // Split the string by commas into an array
    return array_map('trim', explode(',', $cleaned));
}

/**
 * gets the comparaison table between the workers on search/investigate and there possible targets
 * 
 * @param PDO $pdo : database connection
 * @param string|null $turn_number
 * @param string|null $searcher_id
 * 
 * @return array 
 * 
 */
function getSearcherComparisons($pdo, $turn_number = NULL, $searcher_id = NULL) {

    $debug = strtolower(getConfig($pdo, 'DEBUG_REPORT')) === 'true';
    $prefix = $_SESSION['GAME_PREFIX'];

    if ( !isset($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
        echo "turn_number : $turn_number <br>";
    }

    // Define the SQL query based on database type
    if ($_SESSION['DBTYPE'] == 'postgres') {
        $sql = "
            WITH searchers AS (
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
                wa.worker_id AS found_id,
                wa.enquete_val AS found_enquete_val,
                wa.action_choice AS found_action,
                wa.action_params AS found_action_params,
                CONCAT(w.firstname, ' ', w.lastname) AS found_name,
                wo.id AS found_worker_origin_id,
                wo.name AS found_worker_origin_name,
                cw.controller_id AS found_controller_id,
                CONCAT(c.firstname, ' ', c.lastname) AS found_controller_name,
                (
                    SELECT ARRAY_AGG(p.name)
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Metier'
                ) AS found_metier,
                (
                    SELECT ARRAY_AGG(p.name)
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Hobby'
                ) AS found_hobby,
                (
                    SELECT ARRAY_AGG(p.name)
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Discipline'
                ) AS found_discipline,
                (
                    SELECT ARRAY_AGG(p.name)
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Transformation'
                ) AS found_transformation,
                (
                    SELECT ARRAY_AGG((p.other->>'hidden')::INT)
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Transformation'
                ) AS hidden_transformation,
                (s.searcher_enquete_val - wa.enquete_val) AS enquete_difference
            FROM searchers s
            JOIN {$prefix}zones z ON z.id = s.zone_id
            JOIN {$prefix}worker_actions wa ON
                s.zone_id = wa.zone_id AND turn_number = :turn_number AND action_choice IN (%s)
            JOIN {$prefix}workers w ON wa.worker_id = w.id
            JOIN {$prefix}worker_origins wo ON wo.id = w.origin_id
            JOIN {$prefix}controller_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controller = true
            JOIN {$prefix}controllers c ON cw.controller_id = c.ID
            WHERE
                s.searcher_id != wa.worker_id
                AND s.searcher_controller_id != wa.controller_id
        ";
    } else {
        // MySQL version
        $sql = "
            SELECT
                s.searcher_id,
                s.searcher_enquete_val,
                s.searcher_controller_id,
                z.id AS zone_id,
                z.name AS zone_name,
                wa.worker_id AS found_id,
                wa.enquete_val AS found_enquete_val,
                wa.action_choice AS found_action,
                wa.action_params AS found_action_params,
                CONCAT(w.firstname, ' ', w.lastname) AS found_name,
                wo.id AS found_worker_origin_id,
                wo.name AS found_worker_origin_name,
                cw.controller_id AS found_controller_id,
                CONCAT(c.firstname, ' ', c.lastname) AS found_controller_name,
                (
                    SELECT GROUP_CONCAT(p.name SEPARATOR ',')
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Metier'
                ) AS found_metier,
                (
                    SELECT GROUP_CONCAT(p.name SEPARATOR ',')
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Hobby'
                ) AS found_hobby,
                (
                    SELECT GROUP_CONCAT(p.name SEPARATOR ',')
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Discipline'
                ) AS found_discipline,
                (
                    SELECT GROUP_CONCAT(p.name SEPARATOR ',')
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Transformation'
                ) AS found_transformation,
                (
                    SELECT GROUP_CONCAT(CAST(JSON_UNQUOTE(JSON_EXTRACT(p.other, '$.hidden')) AS SIGNED) SEPARATOR ',')
                    FROM {$prefix}worker_powers wp
                    JOIN {$prefix}link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    JOIN {$prefix}powers p ON lpt.power_id = p.ID
                    JOIN {$prefix}power_types pt ON lpt.power_type_id = pt.ID
                    WHERE wp.worker_id = wa.worker_id AND pt.name = 'Transformation'
                ) AS hidden_transformation,
                (s.searcher_enquete_val - wa.enquete_val) AS enquete_difference
            FROM (
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
            ) s
            JOIN {$prefix}zones z ON z.id = s.zone_id
            JOIN {$prefix}worker_actions wa ON
                s.zone_id = wa.zone_id AND turn_number = :turn_number AND action_choice IN (%s)
            JOIN {$prefix}workers w ON wa.worker_id = w.id
            JOIN {$prefix}worker_origins wo ON wo.id = w.origin_id
            JOIN {$prefix}controller_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controller = 1
            JOIN {$prefix}controllers c ON cw.controller_id = c.ID
            WHERE
                s.searcher_id != wa.worker_id
                AND s.searcher_controller_id != wa.controller_id
        ";
    }
    if ( !EMPTY($searcher_id) ) $sql .= sprintf(" AND s.searcher_id = %d", $searcher_id);
    // Whitelisted (asc|desc) — getInvestigateOrder enforces the only safe interpolation surface
    $order = strtoupper(getInvestigateOrder($pdo));
    $sql .= " ORDER BY s.searcher_enquete_val $order, s.searcher_id ASC";
    if ($debug) echo sprintf("sql : %s <br/>", $sql);
    try{
        $investigate_actions = getValidatedInvestigateActionsForSql($pdo);
        $active_actions = "'".implode("','", ACTIVE_ACTIONS)."'";
        if ($debug) echo sprintf("turn_number : %s <br/>", $turn_number);
        if ($debug) echo sprintf("investigate_actions : %s <br/>", $investigate_actions);
        if ($debug) echo sprintf("active_actions : %s <br/>", $active_actions);
        $sql = sprintf($sql, $investigate_actions, $active_actions);

        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->execute();

    } catch (PDOException $e) {
        echo __FUNCTION__."(): Error: " . $e->getMessage();
    }
    if ($debug) echo sprintf("stmt->rowCount() : %s <br/>", $stmt->rowCount());

    // Fetch and return the results
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Build the per-row HTML chunk for one (searcher, found) pair: variant-aware (no CKE / moved / still-here / upgrade).
 * Returns [reportElement, ckePowersFlag, ckeControllerId, ckeControllerName] — the caller passes the last 3 to addWorkerToCKE.
 *
 * @param PDO $pdo
 * @param array $row : one getSearcherComparisons row
 * @param array|null $prevCke : CKE row read before this upsert (NULL = no prior entry)
 * @param array $zoneNameById : map of zone_id => zone name (for the "moved from <zone>" summary)
 * @param array $txtBag : pre-loaded text-config bag (slab templates, variant templates, action labels)
 *
 * @return array
 */
function buildInvestigateReportLine($pdo, $row, $prevCke, $zoneNameById, $txtBag) {
    $REPORTDIFF = $txtBag['REPORTDIFF'];
    $enqDiff = (int)$row['enquete_difference'];

    if      ($enqDiff >= $REPORTDIFF[3]) $currentLevel = 3;
    elseif  ($enqDiff >= $REPORTDIFF[2]) $currentLevel = 2;
    elseif  ($enqDiff >= $REPORTDIFF[1]) $currentLevel = 1;
    elseif  ($enqDiff >= $REPORTDIFF[0]) $currentLevel = 0;
    else    return ['', false, NULL, NULL];

    $disciplines = cleanAndSplitString($row['found_discipline']);
    $discipline_2 = '';
    foreach ($disciplines as $key => $discipline) {
        if ($key == 0) continue;
        $discipline_2 .= sprintf(
            $txtBag['textesFoundDisciplines'][array_rand($txtBag['textesFoundDisciplines'])],
            $discipline
        );
    }

    $hidden_transformation = cleanAndSplitString($row['hidden_transformation']);
    $found_transformation = cleanAndSplitString($row['found_transformation']);
    $transformationTextDiff = ['', '', '', ''];
    foreach ($hidden_transformation as $iteration => $diffval) {
        if ($iteration > 0 && !empty($transformationTextDiff[$diffval]))
            $transformationTextDiff[$diffval] .= ' et ';
        if ($diffval == 0 || $diffval == 3)
            $transformationTextDiff[$diffval] .= $found_transformation[$iteration];
        if ($diffval == 1)
            $transformationTextDiff[$diffval] .= sprintf(
                $txtBag['textesTransformationDiff1'][array_rand($txtBag['textesTransformationDiff1'])],
                $found_transformation[$iteration]
            );
        if ($diffval == 2)
            $transformationTextDiff[$diffval] .= sprintf(
                $txtBag['textesTransformationDiff2'][array_rand($txtBag['textesTransformationDiff2'])],
                $found_transformation[$iteration]
            );
    }

    $text_action_ps = $txtBag['actions'][$row['found_action']]['ps'];
    $text_action_inf = $txtBag['actions'][$row['found_action']]['inf'];
    if ($row['found_action'] == 'claim' && $row['found_action_params'] != '{}') {
        $found_action_params = json_decode($row['found_action_params'], true);
        if (is_array($found_action_params)) {
            if ($found_action_params['claim_controller_id'] == 'null') {
                $text_action_ps .= ' au nom de personne';
                $text_action_inf .= ' au nom de personne';
            } else {
                $controllers = getControllers($pdo, NULL, $found_action_params['claim_controller_id']);
                $claim_suffix = sprintf(' au nom %s %s %s', $txtBag['controllerNameDenominatorThe'], $controllers[0]['firstname'], $controllers[0]['lastname']);
                $text_action_ps .= $claim_suffix;
                $text_action_inf .= $claim_suffix;
            }
        }
    }
    if ($row['found_action'] == 'attack' && $row['found_action_params'] != '{}') {
        $found_action_params = json_decode($row['found_action_params'], true);
        $networkIDs = [];
        $workerIDs = [];
        if (is_array($found_action_params)) {
            foreach ($found_action_params as $param) {
                if (isset($param['attackScope']) && isset($param['attackID'])) {
                    if ($param['attackScope'] === 'network') $networkIDs[] = $param['attackID'];
                    elseif ($param['attackScope'] === 'worker') $workerIDs[] = $param['attackID'];
                }
            }
        }
        if (count($networkIDs) != 0) {
            $suffix = (count($networkIDs) == 1)
                ? ' le réseau '.$networkIDs[0]
                : ' les réseaux '.implode(', ', $networkIDs);
            $text_action_ps .= $suffix;
            $text_action_inf .= $suffix;
        } elseif (count($workerIDs) != 0) {
            $suffix = (count($workerIDs) == 1) ? ' une personne ' : ' plusieurs personnes ';
            $text_action_ps .= $suffix;
            $text_action_inf .= $suffix;
        }
    }

    $originTexte = '';
    if (!in_array($row['found_worker_origin_id'], explode(',', $txtBag['local_origin_list']))) {
        $originTexte = sprintf(
            $txtBag['textesOrigine'][array_rand($txtBag['textesOrigine'])],
            $row['found_worker_origin_name']
        );
    }

    $found_hobby = cleanAndSplitString($row['found_hobby']);
    $found_metier = cleanAndSplitString($row['found_metier']);

    $textesDiff01Array = !empty($transformationTextDiff[0])
        ? $txtBag['textesDiff01TransformationDiff0Array']
        : $txtBag['textesDiff01Array'];
    $texteDiff01 = $textesDiff01Array[array_rand($textesDiff01Array)];

    $slabArgs = [
        $row['found_name'], $found_metier[0], $found_hobby[0],
        $text_action_ps, $text_action_inf, $disciplines[0],
        $transformationTextDiff[0], $transformationTextDiff[1], $originTexte
    ];
    $slabs = [
        0 => vsprintf($texteDiff01[0], $slabArgs),
        1 => vsprintf($texteDiff01[1], $slabArgs),
    ];

    // slab[2] and slab[3] only need building if either current OR prev knows that level
    $needSlab2 = ($currentLevel >= 2) || ($prevCke && !empty($prevCke['discovered_controller_id']));
    $needSlab3 = ($currentLevel >= 3) || ($prevCke && !empty($prevCke['discovered_controller_name']));
    if ($needSlab2) {
        $slabs[2] = sprintf(
            $txtBag['textesDiff2'][array_rand($txtBag['textesDiff2'])],
            $row['found_controller_id'], $transformationTextDiff[2], $discipline_2
        );
    }
    if ($needSlab3) {
        $slabs[3] = sprintf(
            $txtBag['textesDiff3'][array_rand($txtBag['textesDiff3'])],
            $row['found_controller_name']
        );
    }

    if (empty($prevCke)) {
        $prevLevel = -1;
        $prevZone = NULL;
    } else {
        $prevLevel = 0;
        if (!empty($prevCke['discovered_powers']))          $prevLevel = max($prevLevel, 1);
        if (!empty($prevCke['discovered_controller_id']))   $prevLevel = max($prevLevel, 2);
        if (!empty($prevCke['discovered_controller_name'])) $prevLevel = max($prevLevel, 3);
        $prevZone = (int)$prevCke['zone_id'];
    }
    $currentZone = (int)$row['zone_id'];

    $foundName = $row['found_name'];
    $reminderLabel = $txtBag['textesAgentReminderLabel'];

    if ($prevLevel == -1) {
        // No prior CKE — full text (current behaviour)
        $visibleSlabs = [];
        for ($i = 0; $i <= $currentLevel; $i++) $visibleSlabs[] = $slabs[$i];
        $reportElement = '<p>'.implode(' ', $visibleSlabs).'</p>';
    } elseif ($prevZone !== NULL && $prevZone !== $currentZone) {
        // Moved — summary + full known text folded
        $prevZoneName = $zoneNameById[$prevZone] ?? ('zone #'.$prevZone);
        $movedTpl = $txtBag['textesAgentMoved'][array_rand($txtBag['textesAgentMoved'])];
        $summary = sprintf($movedTpl, $foundName, $prevZoneName);
        $maxLevel = max($currentLevel, $prevLevel);
        $foldedSlabs = [];
        for ($i = 0; $i <= $maxLevel; $i++) {
            if (isset($slabs[$i])) $foldedSlabs[] = $slabs[$i];
        }
        $reportElement = '<details><summary>'.$summary.'</summary><p>'.implode(' ', $foldedSlabs).'</p></details>';
    } elseif ($currentLevel <= $prevLevel) {
        // delta <= 0 — "still here" + folded prev
        $stillTpl = $txtBag['textesAgentStillHere'][array_rand($txtBag['textesAgentStillHere'])];
        $summary = sprintf($stillTpl, $foundName);
        $foldedSlabs = [];
        for ($i = 0; $i <= $prevLevel; $i++) {
            if (isset($slabs[$i])) $foldedSlabs[] = $slabs[$i];
        }
        $reportElement = '<details><summary>'.$summary.'</summary><p>'.implode(' ', $foldedSlabs).'</p></details>';
    } else {
        // delta > 0 — new slabs visible + previously-known folded
        $upgradeTpl = $txtBag['textesAgentUpgradeInfo'][array_rand($txtBag['textesAgentUpgradeInfo'])];
        $upgradeText = sprintf($upgradeTpl, $foundName);
        $newSlabs = [];
        for ($i = $prevLevel + 1; $i <= $currentLevel; $i++) $newSlabs[] = $slabs[$i];
        $oldSlabs = [];
        for ($i = 0; $i <= $prevLevel; $i++) {
            if (isset($slabs[$i])) $oldSlabs[] = $slabs[$i];
        }
        $reportElement = '<p>'.$upgradeText.' '.implode(' ', $newSlabs).'</p>';
        if (!empty($oldSlabs)) {
            $reportElement .= '<details><summary>'.$reminderLabel.'</summary><p>'.implode(' ', $oldSlabs).'</p></details>';
        }
    }

    $ckePowersFlag       = ($currentLevel >= 1);
    $ckeControllerId     = ($currentLevel >= 2) ? $row['found_controller_id'] : NULL;
    $ckeControllerName   = ($currentLevel >= 3) ? $row['found_controller_name'] : NULL;

    return [$reportElement, $ckePowersFlag, $ckeControllerId, $ckeControllerName];
}

/**
 * Resolves end-of-turn investigation reports for every active investigator.
 * Sorts searchers by enquete (config-driven asc/desc with age tiebreak) so weak-then-strong dedup gives every searcher a chance to discover something new.
 *
 * @param PDO $pdo : database connection
 * @param array $mechanics : mechanics row
 *
 * @return bool success
 */
function investigateMechanic($pdo, $mechanics) {
    $turn_number = $mechanics['turncounter'];
    echo '<div> <h3> investigateMechanic : </h3> ';
    echo "turn_number : $turn_number <br>";

    $debug = strtolower(getConfig($pdo, 'DEBUG_REPORT')) === 'true';

    $REPORTDIFF = [
        0 => (int)getConfig($pdo, 'REPORTDIFF0'),
        1 => (int)getConfig($pdo, 'REPORTDIFF1'),
        2 => (int)getConfig($pdo, 'REPORTDIFF2'),
        3 => (int)getConfig($pdo, 'REPORTDIFF3'),
    ];
    if ($debug) {
        foreach ($REPORTDIFF as $k => $v) echo "REPORTDIFF$k : $v <br/>";
    }

    $investigations = getSearcherComparisons($pdo, $turn_number, NULL);
    if ($debug) echo sprintf("investigations : %s <br/>", var_export($investigations, true));

    $txtBag = [
        'actions' => [
            'hide'        => ['ps' => getConfig($pdo, 'txt_ps_hide'),        'inf' => getConfig($pdo, 'txt_inf_hide')],
            'passive'     => ['ps' => getConfig($pdo, 'txt_ps_passive'),     'inf' => getConfig($pdo, 'txt_inf_passive')],
            'investigate' => ['ps' => getConfig($pdo, 'txt_ps_investigate'), 'inf' => getConfig($pdo, 'txt_inf_investigate')],
            'attack'      => ['ps' => getConfig($pdo, 'txt_ps_attack'),      'inf' => getConfig($pdo, 'txt_inf_attack')],
            'claim'       => ['ps' => getConfig($pdo, 'txt_ps_claim'),       'inf' => getConfig($pdo, 'txt_inf_claim')],
            'captured'    => ['ps' => getConfig($pdo, 'txt_ps_captured'),    'inf' => getConfig($pdo, 'txt_inf_captured')],
            'dead'        => ['ps' => getConfig($pdo, 'txt_ps_dead'),        'inf' => getConfig($pdo, 'txt_inf_dead')],
        ],
        'textesStartInvestigate'              => getConfig($pdo, 'textesStartInvestigate'),
        'textesDiff01Array'                   => json_decode(getConfig($pdo, 'textesDiff01Array'), true),
        'textesDiff01TransformationDiff0Array'=> json_decode(getConfig($pdo, 'textesDiff01TransformationDiff0Array'), true),
        'textesDiff2'                         => json_decode(getConfig($pdo, 'textesDiff2'), true),
        'textesDiff3'                         => json_decode(getConfig($pdo, 'textesDiff3'), true),
        'textesFoundDisciplines'              => json_decode(getConfig($pdo, 'textesFoundDisciplines'), true),
        'textesTransformationDiff1'           => json_decode(getConfig($pdo, 'textesTransformationDiff1'), true),
        'textesTransformationDiff2'           => json_decode(getConfig($pdo, 'textesTransformationDiff2'), true),
        'textesOrigine'                       => json_decode(getConfig($pdo, 'textesOrigine'), true),
        'local_origin_list'                   => getConfig($pdo, 'local_origin_list'),
        'controllerNameDenominatorThe'        => getConfig($pdo, 'controllerNameDenominatorThe'),
        'textesAgentStillHere'                => json_decode(getConfig($pdo, 'textesAgentStillHere'), true),
        'textesAgentMoved'                    => json_decode(getConfig($pdo, 'textesAgentMoved'), true),
        'textesAgentUpgradeInfo'              => json_decode(getConfig($pdo, 'textesAgentUpgradeInfo'), true),
        'textesAgentReminderLabel'            => getConfig($pdo, 'textesAgentReminderLabel'),
        'REPORTDIFF'                          => $REPORTDIFF,
    ];

    $zoneNameById = [];
    foreach (getZonesArray($pdo) as $z) {
        $zoneNameById[(int)$z['zone_id']] = $z['name'];
    }

    $reportArray = [];

    foreach ($investigations as $row) {
        if ($debug) echo "<div><p> row : ".var_export($row, true)."</p>";

        if (empty($reportArray[$row['searcher_id']])) {
            $reportArray[$row['searcher_id']] = sprintf($txtBag['textesStartInvestigate'], $row['zone_name']);
        }

        $prevCke = getCKEEntry($pdo, $row['searcher_controller_id'], $row['found_id']);

        list($reportElement, $ckePowersFlag, $ckeControllerId, $ckeControllerName) =
            buildInvestigateReportLine($pdo, $row, $prevCke, $zoneNameById, $txtBag);

        if ($reportElement !== '') {
            $reportArray[$row['searcher_id']] .= $reportElement;
        }

        $enqDiff = (int)$row['enquete_difference'];
        if ($enqDiff >= $REPORTDIFF[0]) {
            addWorkerToCKE(
                $pdo,
                $row['searcher_controller_id'],
                $row['found_id'],
                $turn_number,
                $row['zone_id'],
                $ckeControllerId,
                $ckeControllerName,
                $ckePowersFlag
            );

            // Double-agent CKE seeding: same fields propagate to every other controller of the searcher
            try {
                $prefix = $_SESSION['GAME_PREFIX'];
                $sql = sprintf(
                    "SELECT controller_id FROM {$prefix}controller_worker
                    WHERE worker_id = %s AND controller_id != %s",
                    $row['searcher_id'],
                    $row['searcher_controller_id']
                );
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $controllers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($controllers as $controller) {
                    addWorkerToCKE(
                        $pdo,
                        $controller['controller_id'],
                        $row['found_id'],
                        $turn_number,
                        $row['zone_id'],
                        $ckeControllerId,
                        $ckeControllerName,
                        $ckePowersFlag
                    );
                }
            } catch (PDOException $e) {
                echo __FUNCTION__."(): Error SELECT controller_id FROM controller_worker Failed: " . $e->getMessage()."<br />";
            }
        }

        if ($debug) echo "</div>";
    }

    foreach ($reportArray as $worker_id => $report) {
        try {
            updateWorkerAction($pdo, $worker_id, $turn_number, NULL, ['investigate_report' => $report]);
        } catch (Exception $e) {
            echo "updateWorkerAction() failed for worker_id $worker_id: " . $e->getMessage() . "<br />";
            break;
        }
    }

    echo '<p>investigateMechanic : DONE </p> </div>';
    return true;
}