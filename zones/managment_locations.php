<?php
require_once '../base/basePHP.php';
$pageName = 'admin_locations_discovery';

// Get all locations
$locations = $gameReady->query("SELECT id, name, discovery_diff FROM locations ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Get all controllers
$controllers = $gameReady->query("SELECT id, lastname FROM controllers ORDER BY lastname")->fetchAll(PDO::FETCH_ASSOC);

// Get known locations mapping
$knownStmt = $gameReady->query("SELECT controller_id, location_id FROM controller_known_locations");
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
        $content = sprintf('
            %s
            <div style="margin-bottom: 2em; border: 1px solid #ccc; padding: 1em;">
            <h3>%s (discovery %s)</h3>
            <p><strong>Discovered by:</strong></p>
            <ul>',
            ($iteration % 6 === 0) ? '</div><div class="flex">' : '',
            htmlspecialchars($loc['name']),
            htmlspecialchars($loc['discovery_diff'])
        );
        foreach ($controllers as $ctrl):
            $isKnown = isset($knownMap[$loc['id']]) && in_array($ctrl['id'], $knownMap[$loc['id']]);
            $label = htmlspecialchars($ctrl['lastname']);
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
