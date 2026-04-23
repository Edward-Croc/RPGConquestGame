<?php
require_once '../base/basePHP.php';

$pageName = 'new';

if (isset($_SESSION['controller'])){
    $controller_id = $_SESSION['controller']['id'];
}
if (isset($_GET['controller_id'])){
    $controller_id = $_GET['controller_id'];
}
$controllerValues = getControllers($gameReady, NULL, $controller_id);
$recrutment_allowed = true;

$buttonClicked = 'first_come';
$pageTitle = 'Recruter le premier venu';

if ( $_SESSION['DEBUG'] == true )
echo '<p>turncounter: '. (INT)$mechanics['turncounter']
    .'; turn_firstcome_workers: '. getConfig($gameReady, 'turn_firstcome_workers')
    .'; turn_recrutable_workers: '. getConfig($gameReady, 'turn_recrutable_workers')
    .'; start_workers :'. $controllerValues[0]['start_workers']
    .'; turn_recruited_workers :'. $controllerValues[0]['turn_recruited_workers']
    .'; turn_firstcome_workers :'. $controllerValues[0]['turn_firstcome_workers']
.'</p>';

if (isset($_GET['recrutement'])){
    $buttonClicked = 'recrutement';
    $pageTitle = "Recrutement d'un Agent";
    if ( !((
        ( (INT)$mechanics['turncounter'] == 0 ) && ( (INT)$controllerValues[0]['turn_recruited_workers'] < (INT)$controllerValues[0]['start_workers'] )
        ) || (
        ( (INT)$mechanics['turncounter'] > 0 ) && ( (INT)$controllerValues[0]['turn_recruited_workers'] < (INT)getConfig($gameReady, 'turn_recrutable_workers') )
        ) )
    ) $recrutment_allowed = false;
} else {
    if ( !((INT)$controllerValues[0]['turn_firstcome_workers'] < (INT)getConfig($gameReady, 'turn_firstcome_workers')) ) $recrutment_allowed = false;
}
if ( !$recrutment_allowed ){
    require_once '../base/baseHTML.php';
        echo " <div> <h2> $pageTitle </h2> </div>
        <div >
                Le recrutement n'est pas permis !
            </div>
        </body>";
        return 0;
}

$prefix = $_SESSION['GAME_PREFIX'];
// increment recrutment values
$sqlUpdateRecrutementCounter = sprintf(
    "UPDATE {$prefix}controllers SET %1\$s = %1\$s +1 WHERE id = :controller_id",
    $buttonClicked == 'first_come' ? 'turn_firstcome_workers' : 'turn_recruited_workers'
);
$stmtUpdateRecrutementCounter = $gameReady->prepare($sqlUpdateRecrutementCounter);
$stmtUpdateRecrutementCounter->execute([
    ':controller_id' => $controller_id
]);

if ($_SESSION['DEBUG'] == true){
    echo "buttonClicked : $buttonClicked";
    echo"<br />";
    echo"<br />";
}

$nbChoices = 1;
$tmpChoices = getConfig($gameReady, $buttonClicked.'_nb_choices');
if ( !empty($tmpChoices) ) $nbChoices = intval($tmpChoices);

if ($_SESSION['DEBUG'] == true){
    echo "nbChoices : $nbChoices";
    echo"<br />";
    echo"<br />";
}

$powerDisciplineArray = getPowersByType($gameReady,'3', $controller_id, true);
$zonesArray = getZonesArray($gameReady);
if ($_SESSION['DEBUG'] == true){
    echo "powerDisciplineArray: ".var_export($powerDisciplineArray, true)."<br /><br />";
    echo "zonesArray: ".var_export($zonesArray, true). "<br /><br />";
}

require_once '../base/baseHTML.php';

echo "
    <div> <h2> $pageTitle </h2> </div>
    <div >
";

for ($iteration = 0; $iteration < $nbChoices; $iteration++) {
    $newWorker = generateNewWorker($gameReady, $controller_id, $buttonClicked);
    if ($_SESSION['DEBUG'] == true){
        echo "newWorker: ".var_export($newWorker, true)."<br /><br />";
    }
    echo sprintf ('
    <div class="workers">
    <form action="/%10$s/workers/action.php" method="GET">
        <p>
        <strong>%1$s %2$s</strong> de %3$s <br />
        '.getConfig($gameReady, 'textRecrutementJobHobby').' <br />
        <!-- Hidden inputs -->
        <input type="hidden" name="creation" value="true">
        <input type="hidden" name="firstname" value="%1$s">
        <input type="hidden" name="lastname" value="%2$s">
        <input type="hidden" name="origin" value="%3$s">
        <input type="hidden" name="power_hobby" value="%4$s">
        <input type="hidden" name="power_metier" value="%5$s">
        <input type="hidden" name="origin_id" value="%6$s">
        <input type="hidden" name="power_hobby_id" value="%7$s">
        <input type="hidden" name="power_metier_id" value="%8$s">
        <input type="hidden" name="controller_id" value="%9$s">
    ',
    $newWorker['firstname'],
    $newWorker['lastname'],
    $newWorker['origin'],
    $newWorker['power_1']['power_text'],
    $newWorker['power_2']['power_text'],
    $newWorker['origin_id'],
    $newWorker['power_1']['id'],
    $newWorker['power_2']['id'],
    $controller_id,
    $_SESSION['FOLDER']
    );

    // Check Transformation Conditions
    $powerTransformationArray = array();
    $recrutement_transformation_json = getConfig($gameReady, 'recrutement_transformation');
    $recrutement_transformation_array = array();
    if (!empty($recrutement_transformation_json)) {
        $recrutement_transformation_array = json_decode($recrutement_transformation_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo __FUNCTION__."():JSON decoding error: " . json_last_error_msg() . "<br />";
            $recrutement_transformation_array = array();
        }
    }
    if ($_SESSION['DEBUG_TRANSFORM']) echo sprintf("recrutement_transformation_array :%s  <br>", var_export($recrutement_transformation_array,true));
    if (!empty($recrutement_transformation_array['action']) && $recrutement_transformation_array['action'] == 'check' ) {
        // get transformations
        $powerTransformationArray = getPowersByType($gameReady,'4', NULL, false);
        if ($_SESSION['DEBUG_TRANSFORM']) echo sprintf("powerTransformationArray: %s <br />",var_export($powerTransformationArray, true));
        $powerTransformationArray = cleanPowerListFromJsonConditions($gameReady, $powerTransformationArray, $controller_id, NULL, $mechanics['turncounter'], 'on_recrutment' );
        if ( $_SESSION['DEBUG_TRANSFORM']) echo sprintf("powerTransformationArray: %s <br/>", var_export($powerTransformationArray,true));
    }

    $html = sprintf('
        <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
            %s
        </div>
        %s
        <div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">
            %s 
            <div class="control">
                <input type="submit" name="chosir" value="Recruter et Affecter" class="button is-link" />
            </div>
        </div>
    </p></form></div>',
    showDisciplineSelect($gameReady, $powerDisciplineArray),
    (! empty($powerTransformationArray) ) ? sprintf(
            '<div class="field is-grouped is-grouped-multiline is-flex-wrap-wrap">%s</div>',
            showTransformationSelect($gameReady, $powerTransformationArray, TRUE)
        ) : '',
    showZoneSelect($gameReady, $zonesArray, null, false, false, true));
    echo $html;

}
echo "</div>
</body>";

?>