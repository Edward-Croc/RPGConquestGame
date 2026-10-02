<?php

function aiMechanic($pdo, $mechanics)
{
    $turn_number = $mechanics['turncounter'];
    echo '<div> <h3> aiMechanic : </h3> ';

    echo "turn_number : $turn_number <br>";

    $debug = false;
    if (strtolower(getConfig($pdo, 'DEBUG_IA')) == 'true') {
        $debug = true;
    }

    echo '<p>aiMechanic : DONE </p> </div>';

    return true;
}
