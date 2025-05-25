<?php

function aiMechanic($pdo ) {
    echo '<div> <h3> aiMechanic : </h3> ';

    if (empty($turn_number)) {
        $mechanics = getMechanics($pdo);
        $turn_number = $mechanics['turncounter'];
    }
    echo "turn_number : $turn_number <br>";

    $debug = false;
    if (strtolower(getConfig($pdo, 'DEBUG_IA')) == 'true') $debug = true;

    // TODO : Set Controlled by IA actions
    // Upgrade Workers

    // If type is 'passive'
        // Check if worker disapeared (worker_actions turn_number = current_turn action_choice in ('dead', 'captured') and worker_actions turn_number = last_turn  )
            // Become searching
            // Continue
        // Create 1 worker in start zone or adjacent zone
        // Leave workers on passive

    // If type is 'searching'
        // Check if has known enemies above threshhold
            // Become aggressive
            // Continue
        // Create workers in new adjacent zone
        // Set workers to investigate

    // If type is 'aggressive' or 'violent'
        // For zone with known enemies
            // If worker creation in limit -> Create worker in zone
            // Set workers to attack known enemy
        // For workers in zone with no known enemies
            // If zone with known enemies exists and > 1 worker is in current zone
                // Move to zone with known enemies
                // Set workers to attack known enemy
            // Else if 'aggressive'
                // if >1 worker is in current zone
                    // Move to adjacent zone
                // Set workers to investigate
        // If worker creation in limit -> Create worker in start zone

    echo '<p>aiMechanic : DONE </p> </div>';

    return true;
}