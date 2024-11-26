<?php
require_once '../base/base_php.php';

if ( ! $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Redirect the user to the login page if not logged in
    header('Location: connection/login_form.php');
}

if (isset($_SESSION['user_id'])){
    $controler_id = $_SESSION['user_id'];
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
    echo var_export($nameArray, true);
    echo"<br />";
    echo"<br />";
    echo var_export($powerHobbyArray, true);
    echo"<br />";
    echo"<br />";
    echo var_export($powerMetierArray, true);
    echo"<br />";
    echo"<br />";
    echo var_export($powerDisciplineArray, true);
    echo"<br />";
    echo"<br />";
    echo var_export($zonesArray, true). "<br />";
    echo"<br />";
}

require_once '../base/base_html.php';

echo "
    <div> <h2> $pageTitle </h2> </div>
    <div class='flex'>
";

for ($iteration = 0; $iteration < $nbChoices; $iteration++) {
    echo sprintf ('
    <div class="workers"><p>
        %1$s  %2$s de %3$s <br />
        %4$s, %5$s  <br />
    ',
    $nameArray[$iteration]['firstname'],
    $nameArray[$iteration]['lastname'],
    $nameArray[$iteration]['origin'],
    $powerHobbyArray[$iteration]['name'],
    $powerMetierArray[$iteration]['name']
    );

    $disciplinesOptions = '';
    // Display select list of controllers
    foreach ( $powerDisciplineArray as $powerDiscipline) {
        $disciplinesOptions .= "<option value='" . $powerDiscipline['id'] . "'>" . $powerDiscipline['name'] . " </option>";
    }
    echo sprintf(" Discipline:
        <select id='disciplineSelect'>
            <option value=\'\'>Select Discipline</option>
            %s
        </select>
        <br />
        ",
        $disciplinesOptions
    );

    $zoneOptions = '';
    // Display select list of controllers
    foreach ( $zonesArray as $zone) {
        $zoneOptions .= "<option value='" . $zone['id'] . "'>" . $zone['name'] . " </option>";
    }
    echo sprintf(" Zone :
        <select id='zone'>
            <option value=\'\'>Select Zone</option>
            %s
        </select>
        <br />
        ",
        $zoneOptions
    );

    echo "<input type='submit' name='chosir' value='Affecter' /> 
    </p>
    </div>";

}
echo "</div>
</body>";

?>