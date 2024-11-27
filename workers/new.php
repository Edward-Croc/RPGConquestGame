<?php
require_once '../base/base_php.php';

$pageName = 'new';

if ( ! $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Redirect the user to the login page if not logged in
    header('Location: connection/login_form.php');
}

if (isset($_SESSION['controler'])){
    $controler_id = $_SESSION['controler']['id'];
}
if (isset($_GET['controler_id'])){
    $controler_id = $_GET['controler_id'];
}

$buttonClicked = 'first_come';
$pageTitle = 'Recruter le premier venu';
if (isset($_GET['recrutement'])){
    $buttonClicked = 'recrutement';
    $pageTitle = "Recrutement d'un Agent";
}

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
if ( empty($tmpOrigine) || $tmpOrigine == 'rand' ){
    $originsArray = randomWorkerOrigin($gameReady);
    if ($_SESSION['DEBUG'] == true){
        echo var_export($originsArray, true);
        echo"<br />";
        echo"<br />";
    }
    $originList = $originsArray[0]['id'];
} else {
    $originList = $tmpOrigine;
}

$nameArray = randomWorkerName($gameReady, $originList, $nbChoices);
$powerHobbyArray = randomPowersByType($gameReady,'1',$nbChoices);
$powerMetierArray = randomPowersByType($gameReady,'2',$nbChoices);
$powerDisciplineArray = getBasePowers($gameReady,'3', $controler_id);
$zonesArray = getZonesArray($gameReady);
if ($_SESSION['DEBUG'] == true){
    echo "nameArray: ".var_export($nameArray, true)."<br /><br />";
    echo "powerHobbyArray: ".var_export($powerHobbyArray, true)."<br /><br />";
    echo "powerMetierArray: ".var_export($powerMetierArray, true)."<br /><br />";
    echo "powerDisciplineArray: ".var_export($powerDisciplineArray, true);"<br /><br />";
    echo "zonesArray: ".var_export($zonesArray, true). "<br /><br />";
}

require_once '../base/base_html.php';

echo "
    <div> <h2> $pageTitle </h2> </div>
    <div class='flex'>
";

for ($iteration = 0; $iteration < $nbChoices; $iteration++) {
    echo sprintf ('
    <div class="workers">
    <form action="/RPGConquestGame/workers/action.php" method="GET">
        <p>
        %1$s  %2$s de %3$s <br />
        %4$s, %5$s  <br />
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
        <input type="hidden" name="controler_id" value="%9$s">
    ',
    $nameArray[$iteration]['firstname'],
    $nameArray[$iteration]['lastname'],
    $nameArray[$iteration]['origin'],
    $powerHobbyArray[$iteration]['name'],
    $powerMetierArray[$iteration]['name'],
    $nameArray[$iteration]['origin_id'],
    $powerHobbyArray[$iteration]['id'],
    $powerMetierArray[$iteration]['id'],
    $controler_id,
    );

    $disciplinesOptions = '';
    // Display select list of Controlers
    foreach ( $powerDisciplineArray as $powerDiscipline) {
        $disciplinesOptions .= "<option value='" . $powerDiscipline['power_id'] . "'>" . $powerDiscipline['name'] . " </option>";
    }
    echo sprintf(" Discipline:
        <select id='disciplineSelect' name='discipline'>
            <option value=\'\'>Select Discipline</option>
            %s
        </select>
        <br />
        ",
        $disciplinesOptions
    );

    echo showZoneSelect($zonesArray);

    echo "<input type='submit' name='chosir' value='Affecter' /> 
    </p>
    </form>
    </div>";

}
echo "</div>
</body>";

?>