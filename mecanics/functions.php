<?php 

function diceSQL() {
    return "FLOOR(
        RANDOM() * (
            CAST((SELECT value FROM config WHERE name = 'MAXROLL') AS INT)
            - CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
            +  1
        ) + CAST((SELECT value FROM config WHERE name = 'MINROLL') AS INT)
    )";
}

function diceRoll() {
    $diceSQL = diceSQL();
    $sql = "SELECT $diceSQL as roll";

    try{
        // Prepare and execute SQL query
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo "UPDATE config Failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    $roll = $stmt->fetchALL(PDO::FETCH_ASSOC);
    return $roll[0]['roll'];
}

function calculateVals($pdo, $turn_number){

    $sqlArray = [];
    $array = [];
    $array[] = ['enquete', 'passiveInvestigateActions',false];
    $array[] = ['enquete', 'activeInvestigateActions',true];
    $array[] = ['action', 'passiveActionActions',false];
    $array[] = ['action', 'activeActionActions',true];
    $array[] = ['defence', 'passiveDefenceActions',false];
    $array[] = ['defence', 'activeDefenceActions',true];
    echo '<div> calculateVals : <p>';
    foreach ($array as $elements) {
        if ($elements[2]) {
            $valSQL = sprintf("%s_val = (
                COALESCE((
                    SELECT SUM(p.%s)
                    FROM workers AS w
                    LEFT JOIN worker_powers wp ON w.id = wp.worker_id
                    LEFT JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    LEFT JOIN powers p ON lpt.power_id = p.ID
                    WHERE worker_actions.worker_id = w.id
                ), 0)
                + FLOOR(
                    RANDOM() * 
                    ((SELECT CAST(value AS INT) FROM config WHERE name = 'MAXROLL') - 
                    (SELECT CAST(value AS INT) FROM config WHERE name = 'MINROLL') + 1)
                    + (SELECT CAST(value AS INT) FROM config WHERE name = 'MINROLL')
                )
            )",
            $elements[0], $elements[0]);
        } else {
            $valSQL = sprintf("%s_val = (
                COALESCE((
                    SELECT SUM(p.%s)
                    FROM workers AS w
                    LEFT JOIN worker_powers wp ON w.id = wp.worker_id
                    LEFT JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                    LEFT JOIN powers p ON lpt.power_id = p.ID
                    WHERE worker_actions.worker_id = w.id
                ), 0)
                + (SELECT CAST(value AS INT) FROM config WHERE name = 'PASSIVEVAL') 
            )",
            $elements[0], $elements[0]);
        }

        // 
        $config = getConfig($pdo, $elements[1]);
        echo sprintf("Get Config for %s : $config <br /> ", $elements[1]);
        if (!empty($config)){
            $sqlArray[] = sprintf('UPDATE worker_actions SET %1$s 
                WHERE turn_number = %2$s AND action IN (%3$s)', $valSQL, $turn_number, $config );
        }
    }
    echo '</p>';
    foreach ($sqlArray as $sql) {
        echo "<p>DO SQL : <br> $sql <br>";
        try {
            // Prepare and execute SQL query
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } catch (PDOException $e) {
            echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
            return FALSE;
        }
        echo "DONE <br></p>";
    }
    echo '</p></div>';
    return TRUE;
}

function createNewTurnLines($pdo, $turn_number){
    $turn_number = 2; // Example turn number
    $sql = "
        INSERT INTO worker_actions (worker_id, turn_number, zone_id, controler_id)
        SELECT 
            w.id AS worker_id,
            :turn_number AS turn_number,
            w.zone_id AS zone_id,
            cw.controler_id AS controler_id
        FROM workers w
        JOIN controler_worker AS cw ON cw.worker_id = w.id AND is_primary_controler = true;
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':turn_number' => $turn_number]);
    } catch (PDOException $e) {
        echo __FUNCTION__." (): sql FAILED : ".$e->getMessage()."<br />$sql<br/>";
        return FALSE;
    }
}

function getSearcherComparisons($pdo, $threshold = 0, $searcher_id = NULL, $turn_number = NULL ) {
    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
    }
    echo "turn_number : $turn_number <br>";
    // Define the SQL query
    $sql = "
        WITH searchers AS (
            SELECT 
                wa.worker_id AS searcher_id,
                wa.controler_id AS searcher_controler_id,
                wa.enquete_val AS searcher_enquete_val,
                wa.zone_id
            FROM 
                worker_actions wa
            WHERE 
                wa.action IN ('passive', 'investigate')
                AND turn_number = :turn_number
        )
        SELECT 
            s.searcher_id,
            s.searcher_enquete_val,
            z.name AS zone_name,
            wa.worker_id AS found_id,
            wa.enquete_val AS found_enquete_val,
            wa.action AS found_action,
            wa.action_params AS found_action_params,
            CONCAT(w.firstname, ' ', w.lastname) AS found_name,
            wo.id AS found_worker_origin_id,
            wo.name AS found_worker_origin_name,
            cw.controler_id AS found_controler_id,
            CONCAT(c.firstname, ' ', c.lastname) AS found_controler_name,
            (
                SELECT ARRAY_AGG(p.name)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Metier'
            ) AS found_metier,
            (
                SELECT ARRAY_AGG(p.name)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Hobby'
            ) AS found_hobby,
            (
                SELECT ARRAY_AGG(p.name)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Discipline'
            ) AS found_discipline,
            (
                SELECT ARRAY_AGG(p.name)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Transformation'
            ) AS found_transformation,
            (
                SELECT ARRAY_AGG((p.other->>'hidden')::INT)
                FROM worker_powers wp
                JOIN link_power_type lpt ON wp.link_power_type_id = lpt.ID
                JOIN powers p ON lpt.power_id = p.ID
                JOIN power_types pt ON lpt.power_type_id = pt.ID
                WHERE wp.worker_id = wa.worker_id AND pt.name = 'Transformation'
            ) AS hidden_transformation,
            (s.searcher_enquete_val - wa.enquete_val) AS enquete_difference
        FROM searchers s
        JOIN zones z ON z.id = s.zone_id
        JOIN worker_actions wa ON 
                s.zone_id = wa.zone_id AND turn_number = :turn_number
        JOIN workers w ON wa.worker_id = w.ID
        JOIN worker_origins wo ON wo.id = w.origin_id
        JOIN controler_worker cw ON wa.worker_id = cw.worker_id AND is_primary_controler = true
        JOIN controlers c ON cw.controler_id = c.ID
        WHERE
            s.searcher_id != wa.worker_id
            AND s.searcher_controler_id != wa.controler_id
            AND (s.searcher_enquete_val - wa.enquete_val) >= :threshold
    ";
    if ( !EMPTY($searcher_id) ) $sql .= " AND s.searcher_id = :searcher_id";

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    if ( !EMPTY($searcher_id) ) $stmt->bindParam(':searcher_id', $searcher_id);
    $stmt->bindParam(':turn_number', $turn_number);
    $stmt->bindParam(':threshold', $threshold);
    $stmt->execute();

    // Fetch and return the results
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cleanAndSplitString($input) {
    // Remove curly braces and quotes
    $cleaned = str_replace(['{', '}', '"'], '', $input);
    // Split the string by commas into an array
    return array_map('trim', explode(',', $cleaned));
}

function investigateMecanic($pdo ) {
    echo '<div> investigateMecanic :';
    $investigations = getSearcherComparisons($pdo);
    $rapportArray = [];

    $txtArray = [];
    $txtArray['passive']['ps'] = getConfig($pdo, 'txt_ps_passive');
    $txtArray['investigate']['ps'] = getConfig($pdo, 'txt_ps_investigate');
    $txtArray['attack']['ps'] = getConfig($pdo, 'txt_ps_attack');
    $txtArray['claim']['ps'] = getConfig($pdo, 'txt_ps_claim');
    $txtArray['passive']['inf'] = getConfig($pdo, 'txt_inf_passive');
    $txtArray['investigate']['inf'] = getConfig($pdo, 'txt_inf_investigate');
    $txtArray['attack']['inf'] = getConfig($pdo, 'txt_inf_attack');
    $txtArray['claim']['inf'] = getConfig($pdo, 'txt_inf_claim');

    foreach ($investigations as $row) {
        // Build Rapport :
        if (strtolower(getConfig($pdo, 'DEBUG_RAPPORT')) == 'true') 
            echo var_export($row, true);

        if ( empty($rapportArray[$row['searcher_id']]) )
            $rapportArray[$row['searcher_id']] = sprintf( "<p> Dans le quartier %s.</p>", $row['zone_name'] );

        $discipline = cleanAndSplitString($row['found_discipline']);
        $discipline_2 = '';
        if (! empty($discipline[1]) ) 
            $discipline_2 = sprintf("Et une maitrise de la discipline %s.", $discipline[1]);
        
        // transformation
        $hidden_transformation = cleanAndSplitString($row['hidden_transformation']);
        $found_transformation = cleanAndSplitString($row['found_transformation']);
        $transformationTextDiff[0] = '';
        $transformationTextDiff[1] = '';
        $transformationTextDiff[2] = '';
        $transformationTextDiff[3] = '';
        for ($iteration = 0; $iteration < count($hidden_transformation); $iteration++) {
            $diffval =  $hidden_transformation[$iteration];
            // C'est probablement un (transformation).
            $transformationTextDiff[$diffval] .= $found_transformation[$iteration];
        }

        $text_action_ps = $txtArray[$row['found_action']]['ps'];
        $text_action_inf = $txtArray[$row['found_action']]['inf'];
        if ( !empty($row['found_action_params']) ) {
            $text_action_ps .= ' tada '; 
            $text_action_inf .= ' tada '; 
        }

       $originTexte = '';
        $local_origin_list = getConfig($pdo, 'local_origin_list');
        if (!in_array($row['found_worker_origin_id'], explode(',',$local_origin_list))) {
            $textesOrigine = [
                "Etrange pour un %s. ",
                "En plus, c'est un %s. ",
                "Surtout que c'est un %s. "
            ];
            $originTexte = sprintf($textesOrigine[array_rand($textesOrigine)], $row['found_worker_origin_name']);
        }
        $found_hobby = cleanAndSplitString($row['found_hobby']);
        $found_metier = cleanAndSplitString($row['found_metier']);

        $rapport = "<p>";

        /*
        (nom(id)) - %1$s
        (metier) - %7$s/%2$s
        (hobby) - %3$s
        (action_ps) - %4$s
        (action_inf) - %5$s
        (discipline) - %6$s
        */
        $textesDiff01Array = [[
                'J\'ai vu un %3$s du nom de %1$s qui %4$s dans ma zone. ',
                'C\'est à la base un %2$s mais je suis sûr qu\'il possède aussi la discipline de %4$s. '
            ],[
                'Nous avons repéré un %2$s du nom de %1$s qui %4$s dans notre quartier. ',
                'En poussant nos recherches il s\'avère qu\'il maitrise %6$s. Il est aussi %3$s, mais cette information n\'est pas pertinente. '
            ],[
                'J\'ai trouvé %1$s %7$s qui n\'est clairement pas un agent à nous c\'est un %2$s et un %3$s. ', 
                'Il démontre une légère maitrise de la discipline %6$s. '
            ],[
                'Je me suis rendu compte que %1$s, que je prenais pour un simple %3$s, %4$s dans le coin. ',
                'C\'était louche, alors j\'ai enquêté et trouvé qu\'il a en réalité des pouvoirs de %4$s, ce qui en fait un %2$s un peu trop spécial. ' 
            ],[
                'On a suivi %1$s parce qu\'on l\'a repéré en train de %4$s, ce qui nous a mis la puce à l\'oreille. C\'est normalement un %2$s mais on a découvert qu\'il était aussi %3$s. ',
                'Cela dit, le vrai problème, c\'est qu\'il semble maîtriser %6$s, au moins partiellement. ',
        ]];
        // ! (transformation0)
        if (!empty( $transformationTextDiff[0]))
            $textesDiff01Array = [[
                'Nous avons repéré un %7$s du nom de %1$s qui %4$s dans notre quartier. ',
                'En poussant nos recherches il s\'avère qu\'il maitrise %6$s. Il est aussi %3$s, mais cette information n\'est pas pertinente. '
            ],[
                'J\'ai trouvé %1$s un %7$s qui n\'est clairement pas un agent à nous c\'est un %2$s et un %3$s. ', 
                'Il démontre une légère maitrise de la discipline %6$s. '
            ],[
                'Je me suis rendu compte qu\'un %7$s, %4$s dans le coin. ',
                'C\'était louche, alors j\'ai enquêté et trouvé qu\'il a en réalité des pouvoirs de %4$s, ce qui en fait un %2$s un peu trop spécial. '
            ]];
        $texteDiff01 = $textesDiff01Array[array_rand($textesDiff01Array)];


        // Diff 0
        $rapport = sprintf($texteDiff01[0],
            sprintf('%s (%s)',$row['found_name'], $row['found_id']), // (nom(id)) - %1$s
            $found_metier[0], // (metier) - %2$
            $found_hobby[0], // (hobby) - %3$s
            $text_action_ps, // (action_ps) - %4$s
            $text_action_inf, // (action_inf) - %5$s           
            $discipline[0], // (discipline) - %6$s
            $transformationTextDiff[0], // (transformation0) - %7$s
        );

        // Diff 1
        $rapport .= sprintf($texteDiff01[1],
            sprintf('%s (%s)',$row['found_name'], $row['found_id']), // (nom(id)) - %1$s
            $found_metier[0], // (metier) - %2$
            $found_hobby[0], // (hobby) - %3$s
            $text_action_ps, // (action_ps) - %4$s
            $text_action_inf, // (action_inf) - %5$s           
            $discipline[0], // (discipline) - %6$s
            $transformationTextDiff[0], // (transformation0) - %7$s
        );

        // Diff 2
        $textesDiff2 = [
            "En plus, sa famille a des liens avec le réseau %s. ",
            "Il fait partie du réseau %s. ",
            "En creusant, il est rattaché au réseau %s. ",
            "Il reçoit un soutient financier du réseau %s. ",
            "Il traîne avec le réseau %s. "
        ];
        $rapport .= sprintf($textesDiff2[array_rand($textesDiff2)], $row['found_controler_id'] );

        // Diff 3
        $textesDiff3 = [
            "Ce réseau répond à %s. ",
            "A partir de là on a pu remonter jusqu'à %s. ",
            "Du coup, il travaille forcément pour %s. ",
            "Nous l'avons vu rencontrer en personne %s. ",
            "Ce qui veut dire que c'est un des types de %s. "
        ];
        $rapport .= sprintf($textesDiff3[array_rand($textesDiff3)], $row['found_controler_name']);

        // Debug rapport
        if (strtolower(getConfig($pdo, 'DEBUG_RAPPORT')) == 'true') {
            $rapport .= "Searcher ID: {$row['searcher_id']}, Searcher Enquete Val: {$row['searcher_enquete_val']}, ";
            $rapport .= "Found ID: {$row['found_id']}, Found Enquete Val: {$row['found_enquete_val']}, ";
            $rapport .= "Difference: {$row['enquete_difference']}\n";
        }
        $rapport .= '</p>';
        $rapportArray[$row['searcher_id']] .= $rapport;
    }

    if (strtolower(getConfig($pdo, 'DEBUG_RAPPORT')) == 'true') 
        echo var_export( $rapportArray, true);
    echo '<div>';
}