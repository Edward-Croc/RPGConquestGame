<?php
// Include-only page — block direct HTTP access.
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(403);
    exit();
}

    // Get Zones information
    $zones = getZonesArray($gameReady);
    // Extract map file
    $map_file = getConfig($gameReady, 'map_file');

    $imgString = '';
    if (! empty($map_file )) $imgString = sprintf(
        '<img src="/%s/img/%s" alt="%s" style="max-width:100%%; height:auto;">',
        $_SESSION['FOLDER'],
        htmlspecialchars($map_file),
        htmlspecialchars(getConfig($gameReady, 'map_alt'))
    );

    $controllerWorkers = !empty($_SESSION['controller']['id'])
        ? (getWorkersByController($gameReady, $_SESSION['controller']['id']) ?? [])
        : [];

?>

<div class="section zones">
    <div class="container">
        <h2 class="title is-3">Zones</h2>
        <div class="box mb-4">
            <h4 class="title is-5" onclick="toggleDescription('carte')" style="cursor: pointer;">Carte</h4>
            <div id="description-carte" style="display: none;">
                <?php echo $imgString; ?>
            </div>
        </div>
        <?php
        // Display list of Zones

        $controllerLastNameDenominatorOf = getConfig($gameReady, 'controllerLastNameDenominatorOf');
        $sessionCid = isset($_SESSION['controller']['id']) ? (int)$_SESSION['controller']['id'] : null;
        $bypassVisibility = !empty($_SESSION['is_privileged']) && $sessionCid === null;
        foreach ($zones as $zone) {
            if (!canControllerSeeZone($gameReady, $zone, $sessionCid, $bypassVisibility)) continue;
            $description = htmlspecialchars($zone['description']);
            $zoneName = htmlspecialchars($zone['name']);
            $zoneId = htmlspecialchars($zone['zone_id']);
            $controllerBanner = (!empty($zone['controller_id']))
                ? sprintf(
                    '<span class="tag is-warning ml-2">Sous la bannière %s %s</span>',
                    $controllerLastNameDenominatorOf,
                    htmlspecialchars($zone['claimer_lastname'])
                )
                : '';
            $knownSecrets = !empty($_SESSION['controller']['id'])
                ? showcontrollerKnownSecrets($gameReady, $_SESSION['controller']['id'], $zone['zone_id'])
                : '';
            $agentLists = !empty($_SESSION['controller']['id'])
                ? showZoneAgents($gameReady, $_SESSION['controller']['id'], $zone['zone_id'], $mechanics, $controllerWorkers)
                : '';
            $ourControl = (!empty($_SESSION['controller']['id']) && $zone['holder_controller_id'] == $_SESSION['controller']['id'])
                ? '<span class="tag is-danger ml-2">Sous notre contrôle</span><br>'
                : '';

            echo sprintf('
                <div class="box mb-4" id="zone-%2$s">
                    <h3 class="title is-5" onclick="toggleDescription(\'%2$s\')" style="cursor: pointer;">
                        %1$s <span class="has-text-grey-light">(%2$s)</span> %3$s %6$s
                    </h3>
                    <div id="description-%2$s" style="display: none;">
                        <p class="mb-2"><i>%4$s</i></p>
                        %5$s
                        %7$s
                    </div>
                </div>
                ',
                $zoneName,
                $zoneId,
                $controllerBanner,
                $description,
                $knownSecrets,
                $ourControl,
                $agentLists
            );
        }
        ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var match = (window.location.hash || '').match(/^#zone-(\w+)$/);
        if (!match) return;
        var id = match[1];
        var description = document.getElementById('description-' + id);
        if (description) description.style.display = 'block';
        var box = document.getElementById('zone-' + id);
        if (box) box.scrollIntoView();
    });
</script>