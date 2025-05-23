<?php
require_once '../base/basePHP.php';

$pageName = 'new';

if ( ! $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Redirect the user to the login page if not logged in
    header('Location: connection/loginForm.php');
}

if (isset($_SESSION['controller'])){
    $controller_id = $_SESSION['controller']['id'];
}
if (isset($_GET['controller_id'])){
    $controller_id = $_GET['controller_id'];
}
$controllerValues = getControllers($gameReady, NULL, $controller_id);
$recrutment_allowed = TRUE;

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
    ) $recrutment_allowed = FALSE;
} else {
    if ( !((INT)$controllerValues[0]['turn_firstcome_workers'] < (INT)getConfig($gameReady, 'turn_firstcome_workers')) ) $recrutment_allowed = FALSE;
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

// increment recrutment values
$sqlUpdateRecrutementCounter = sprintf(
    'UPDATE controllers SET %1$s = %1$s +1 WHERE id = :controller_id',
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

$tmpOrigine = getConfig($gameReady, $buttonClicked.'_origin_list');
$originList = null;
if ( !empty($tmpOrigine) && $tmpOrigine != 'rand' ){
    $originList = $tmpOrigine;
}

// TODO : Allow locking certain origins by controler
$nameArray = randomWorkerName($gameReady, $nbChoices, $originList);
// TODO : Allow locking certain Hobbys/Metiers by origin or controler !
$powerHobbyArray = randomPowersByType($gameReady,'1',$nbChoices);
$powerMetierArray = randomPowersByType($gameReady,'2',$nbChoices);
$powerDisciplineArray = getPowersByType($gameReady,'3', $controller_id, TRUE);
$zonesArray = getZonesArray($gameReady);
if ($_SESSION['DEBUG'] == true){
    echo "nameArray: ".var_export($nameArray, true)."<br /><br />";
    echo "powerHobbyArray: ".var_export($powerHobbyArray, true)."<br /><br />";
    echo "powerMetierArray: ".var_export($powerMetierArray, true)."<br /><br />";
    echo "powerDisciplineArray: ".var_export($powerDisciplineArray, true);"<br /><br />";
    echo "zonesArray: ".var_export($zonesArray, true). "<br /><br />";
}

require_once '../base/baseHTML.php';

echo "
    <div> <h2> $pageTitle </h2> </div>
    <div >
";

for ($iteration = 0; $iteration < $nbChoices; $iteration++) {
    echo sprintf ('
    <div class="workers">
    <form action="/RPGConquestGame/workers/action.php" method="GET">
        <p>
        %1$s  %2$s de %3$s <br />
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
    $nameArray[$iteration]['firstname'],
    $nameArray[$iteration]['lastname'],
    $nameArray[$iteration]['origin'],
    $powerHobbyArray[$iteration]['power_text'],
    $powerMetierArray[$iteration]['power_text'],
    $nameArray[$iteration]['origin_id'],
    $powerHobbyArray[$iteration]['id'],
    $powerMetierArray[$iteration]['id'],
    $controller_id,
    );

    echo showDisciplineSelect($gameReady, $powerDisciplineArray);

    // Check Transformation Conditions
    $recrutement_transformation_json = getConfig($gameReady, 'recrutement_transformation');
    $recrutement_transformation_array = json_decode($recrutement_transformation_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo __FUNCTION__."():JSON decoding error: " . json_last_error_msg() . "<br />";
        $recrutement_transformation_array = array();
    }
    if ($_SESSION['DEBUG_TRANSFORM']) echo sprintf("recrutement_transformation_array :%s  <br>", var_export($recrutement_transformation_array,true));
    if (!empty($recrutement_transformation_array['action']) && $recrutement_transformation_array['action'] == 'check' ) {
        // get transformations
        $powerTransformationArray = getPowersByType($gameReady,'4', NULL, FALSE);
        if ($_SESSION['DEBUG_TRANSFORM']) echo sprintf("powerTransformationArray: %s <br />",var_export($powerTransformationArray, true));
        $powerTransformationArray = cleanPowerListFromJsonConditions($gameReady, $powerTransformationArray, $controller_id, NULL, $mechanics['turncounter'], 'on_recrutment' );
        if ( $_SESSION['DEBUG_TRANSFORM']) echo sprintf("powerTransformationArray: %s <br/>", var_export($powerTransformationArray,true));
        if (! empty($powerTransformationArray) )
            echo showTransformationSelect($gameReady, $powerTransformationArray, TRUE);
    }

    echo showZoneSelect($gameReady, $zonesArray, FALSE, FALSE);

    echo "<input type='submit' name='chosir' value='Affecter' />
    </p>
    </form>
    </div>";

}
echo "</div>
</body>";

?>