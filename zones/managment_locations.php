<?php
require_once '../base/basePHP.php';
$pageName = 'admin_locations_discovery';


$prefix = $_SESSION['GAME_PREFIX'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $gameReady->prepare("DELETE FROM {$prefix}locations WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
    }
}
if (isset($_POST['toggle_destruction'])) {
    $stmt = $gameReady->prepare("SELECT * FROM {$prefix}locations WHERE id = ?");
    $stmt->execute([$_POST['toggle_destruction']]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    $activate_json = json_decode($location['activate_json'], true);
    updateLocation($gameReady, $location, $activate_json);
}

// Get all locations
$locations = $gameReady->query("
    SELECT l.id, l.name, l.discovery_diff, l.description, l.activate_json, z.name AS zone_name
    FROM {$prefix}locations AS l
    LEFT JOIN {$prefix}zones AS z ON l.zone_id = z.id
    ORDER BY l.id, z.id
")->fetchAll(PDO::FETCH_ASSOC);

// Get all controllers
$controllers = $gameReady->query("SELECT id, lastname FROM {$prefix}controllers ORDER BY lastname")->fetchAll(PDO::FETCH_ASSOC);

// Get known locations mapping
$knownStmt = $gameReady->query("SELECT controller_id, location_id FROM {$prefix}controller_known_locations");
$knownMap = [];
while ($row = $knownStmt->fetch(PDO::FETCH_ASSOC)) {
    $knownMap[$row['location_id']][] = $row['controller_id'];
}

require_once '../base/baseHTML.php';
echo '
    <div class="managment">
    <h1>Location Discovery Administration</h1>
    <div class="content"><div class="flex">';
    $iteration = 0;
    foreach ($locations as $loc):
        // Handle flex div wrapping
        if ($iteration % 6 === 0) {
            echo ' </div><div class="flex"> ';
        }

        $toggleUpdateLocation = '';
        $activate_json = json_decode($loc['activate_json'], true);
        if (!empty($activate_json['update_location'])) {
            $toggleUpdateLocation = sprintf(
                '<!-- Action toggle destruction/repair -->
                    <form method="POST">
                        <input type="hidden" name="toggle_destruction" value="%1$s">
                        <button type="submit">Toggle Destruction/Repair</button>
                    </form>
                ',
                $loc['id']
            );
        }
        $content = sprintf('
            <div style="margin-bottom: 2em; border: 1px solid #ccc; padding: 1em;">
            <h3>%2$s (discovery %3$s)</h3>
            <p>
                <h5 onclick="var d=this.nextElementSibling;d.style.display=d.style.display===\'none\'?\'block\':\'none\';">Description</a>:
                </h5>
                <span style="display:none;"> (%6$s) %4$s </span>
            </p>
            <p>
                <h5 onclick="var d=this.nextElementSibling;d.style.display=d.style.display===\'none\'?\'block\':\'none\';">Actions</a>:
                </h5>
                <span style="display:none;">
                    <!-- Action Delete location -->
                    <form method="POST">
                        <input type="hidden" name="delete_id" value="%1$s">
                        <button type="submit">Delete</button>
                    </form>
                    %5$s
                </span>
            </p>
            <h5>Discovered by:</h5>
            <ul>',
            $loc['id'],
            $loc['name'],
            $loc['discovery_diff'],
            $loc['description'],
            $toggleUpdateLocation,
            $loc['zone_name']
        );
        foreach ($controllers as $ctrl):
            $isKnown = isset($knownMap[$loc['id']]) && in_array($ctrl['id'], $knownMap[$loc['id']]);
            $label = $ctrl['lastname'];
            $content .= sprintf(
                '<li style="color: %s">%s %s </li>', 
                $isKnown ? 'green' : 'gray',
                $isKnown ? "✔️" : "❌",
                $label
            );
        endforeach;
        $content .= "</ul></div>";
        echo $content;
        $iteration ++;
    endforeach;
?>        
    </div>
</div>
</div>
</body>
