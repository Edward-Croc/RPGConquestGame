<?php

function IAMecanic($pdo ) {
    echo '<div> <h3> IAMecanic : </h3> ';

    if (empty($turn_number)) {
        $mecanics = getMecanics($pdo);
        $turn_number = $mecanics['turncounter'];
    }
    echo "turn_number : $turn_number <br>";

    $debug = FALSE;
    if (strtolower(getConfig($pdo, 'DEBUG_IA')) == 'true') $debug = TRUE;

    // TODO : Set Controlled by IA actions
    // If type is 'passive'
        // Check if worker disapeared (worker_actions turn_number = current_turn action_choice in ('dead', 'captured') and worker_actions turn_number = last_turn  )
            // Become serching
            // Continue
        // Create 1 worker in start zone or adjacent zone
        // Leave workers on passive

    // If type is 'serching'
        // Check if has known enemies above threshhold
            // Become agressive
            // Continue
        // Create workers in new adjacent zone
        // Set workers to investigate

    // If type is 'agressive'
        // For zone with known enemies
            // If worker creation in limiot -> Create worker in zone
            // Set workers to attack known enemy
        // For workers in zone with no known enemies
            // If zone with known enemies exists and >1 worker is in current zone
                // Move to zone with non known enemies
                // Set workers to attack known enemy
            // Else
                // if >1 worker is in current zone
                    // Move to adjacent zone
                // Set workers to investigate

    echo '</div>';

    return TRUE;
}