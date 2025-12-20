<?php
    $title = 'Admin - Create Perfect Agent';

    // Build form and call workers/action.php
    // Select a controller from controllers
    $controllerValues = getControllers($gameReady, NULL, NULL, FALSE);

    // Select a zone from zones
    $zonesArray = getZonesArray($gameReady);

    // Select one origin from worker_origins
    // Select one firstname and one lastname from worker_names where origin_id = selected origin
    $prefix = $_SESSION['GAME_PREFIX'];
    try{
        // Get all values from worker_names
        $sql = "SELECT * FROM {$prefix}worker_names
        JOIN {$prefix}worker_origins ON worker_names.origin_id = worker_origins.ID";
        $stmt = $gameReady->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        echo  __FUNCTION__."(): $sql failed: " . $e->getMessage()."<br />";
        return NULL;
    }
    // Fetch the results
    $workerOriginsNames = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $originOptionsCheck = array();
    $originOptions = '';
    $firstnameOptions = '';
    $lastnameOptions = '';
    foreach ($workerOriginsNames as $workerOriginName) {
        // Create options for each origin, firstname and lastname
        // only get each origin once
        if ( !in_array($workerOriginName['origin_id'], $originOptionsCheck) ) {
            $originOptionsCheck[] = $workerOriginName['origin_id'];
            $originOptions .= sprintf(
                '<option value="%1$s">%2$s</option>',
                $workerOriginName['origin_id'],
                $workerOriginName['name']
            );
        }
        $firstnameOptions .= sprintf(
            '<option value="%1$s">%1$s (%2$s)</option>',
            $workerOriginName['firstname'],
            $workerOriginName['name']
        );
        $lastnameOptions .= sprintf(
            '<option value="%1$s">%1$s (%2$s)</option>',
            $workerOriginName['lastname'],
            $workerOriginName['name']
        );
    }
    $showOriginSelect = sprintf('
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="origin_id" name="origin_id">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        'origine',
        $originOptions
    );
    $showFirstnameSelect = sprintf('
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="firstname" name="firstname">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        'Firstname',
        $firstnameOptions
    );
    $showLastnameSelect = sprintf('
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="lastname" name="lastname">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        'Lastname',
        $lastnameOptions
    );

    // Select one power_hobby from powers where type_id = 1
    $powerHobbyArray = getPowersByType($gameReady,'1', null, false);
    $hobbyOptions = '';
    foreach ($powerHobbyArray as $powerHobby) {
        $hobbyOptions .= "<option value='" . htmlspecialchars($powerHobby['link_power_type_id']) . "'>" . htmlspecialchars($powerHobby['power_text']) . "</option>";
    }
    $showHobbySelect = sprintf('
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="power_hobby_id" name="power_hobby_id">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        htmlspecialchars(getPowerTypesDescription($gameReady, 'Hobby')),
        $hobbyOptions
    );
    // Select one power_metier from powers where type_id = 2
    $powerJobArray = getPowersByType($gameReady,'2', null, false);
    $jobOptions = '';
    foreach ($powerJobArray as $powerJob) {
        $jobOptions .= "<option value='" . htmlspecialchars($powerJob['link_power_type_id']) . "'>" . htmlspecialchars($powerJob['power_text']) . "</option>";
    }
    $showJobSelect = sprintf('
            <div class="control for-select">
                <div class="select is-fullwidth">
                    <select id="power_metier_id" name="power_metier_id">
                        <option value="">Sélectionner %s</option>
                        %s
                    </select>
                </div>
            </div>
        ',
        htmlspecialchars(getPowerTypesDescription($gameReady, 'Metier')),
        $jobOptions
    );
    // otionnal Select one power_discipline from powers where type_id = 3
    $powerDisciplineArray = getPowersByType($gameReady,'3', null, false);
    // otionnal Select one power_transformation from powers where type_id = 4
    $powerTransformationArray = getPowersByType($gameReady,'4', null, false);
    // Submit to workers/action.php with creation = true
    /*
    *     "creation"="true"
    *     controller_id
    *     firstname
    *     lastname
    *     origin
    *     origin_id
    *     power_hobby
    *     power_metier
    *     power_hobby_id
    *     power_metier_id
    *     discipline
    *     transformation
    *     zone
    */
    $html = sprintf('
        <div class="workers">
            <h2> %2$s </h2>
            <form action="/%1$s/workers/action.php" method="GET"><p>
                <input type="hidden" name="creation" value="true">
                <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">%11$s</div>
                <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">%8$s %9$s %10$s</div>
                <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">%3$s %4$s</div>
                <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">%5$s %6$s</div>
                <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
                    %7$s 
                    <div class="control">
                        <input type="submit" name="chosir" value="Recruter et Affecter" class="button is-link" />
                    </div>
                </div>
            </p></form>
        </div>',
        $_SESSION['FOLDER'],
        $title,
        $showJobSelect,
        $showHobbySelect,
        showDisciplineSelect($gameReady, $powerDisciplineArray, false),
        showTransformationSelect($gameReady, $powerTransformationArray, false),
        showZoneSelect($gameReady, $zonesArray),
        $showOriginSelect,
        $showFirstnameSelect,
        $showLastnameSelect,
        showControllerSelect($controllerValues)
    );
    echo $html;
?>