<?php
$noConnection = true;
require_once '../base/basePHP.php';
$pageName = 'systemPresentation';

require_once '../base/baseHTML.php';

$timeValue = (string) getConfig($gameReady, 'timeValue');
$zoneType = (string) getConfig($gameReady, 'textForZoneType');
$labelInvestigate = ucfirst((string) getConfig($gameReady, 'txt_inf_investigate'));
$labelPassive = ucfirst((string) getConfig($gameReady, 'txt_inf_passive'));
$labelHide = ucfirst((string) getConfig($gameReady, 'txt_inf_hide'));
$minRoll = (int) getConfig($gameReady, 'MINROLL');
$maxRoll = (int) getConfig($gameReady, 'MAXROLL');
$passiveVal = (int) getConfig($gameReady, 'PASSIVEVAL');
$claimEnabled = in_array(getConfig($gameReady, 'claimMode'), ['worker', 'worker_leader'], true);
$agentLocationAttack = getConfig($gameReady, 'locationAttackMode') === 'agent_attack_defence';
$ressourcesEnabled = getConfig($gameReady, 'ressource_management') == 'TRUE';
$captureSet = getConfig($gameReady, 'guideCaptures');
$hideDiscretion = (int) getConfig($gameReady, 'HIDE_ENQUETE_FLAT_BONUS');
$hideDefence = (int) getConfig($gameReady, 'HIDE_DEFENCE_FLAT_BONUS');
$defendLocationBonus = (int) getConfig($gameReady, 'DEFEND_LOCATION_DEFENCE_FLAT_BONUS');
$ownedArtefactsTitle = rtrim((string) getConfig($gameReady, 'textOwnedArtefacts'), ' :');

// What an action adds on one axis : a die when it is active on that axis, the fixed value otherwise.
$actionRoll = function (string $axis, string $action) use ($gameReady, $minRoll, $maxRoll, $passiveVal): string {
    $activeActions = explode(',', str_replace("'", '', (string) getConfig($gameReady, 'active' . $axis . 'Actions')));
    if (!in_array($action, $activeActions, true)) {
        return (string) $passiveVal;
    }
    return $minRoll === 1 ? '1d' . $maxRoll : sprintf('un dé de %d à %d', $minRoll, $maxRoll);
};

// Actions an agent keeps from one turn to the next, the others fall back to watching.
$continuingActions = [];
foreach (['investigate', 'hide', 'claim', 'attack', 'attack_location', 'defend_location'] as $action) {
    if ((int) getConfig($gameReady, 'continuing_' . $action . '_action') === 1) {
        $continuingActions[] = (string) getConfig($gameReady, 'txt_inf_' . $action);
    }
}

?>
<div class="factions guide">
<h2 class="title is-3">Guide du joueur</h2>
<div class="box guide-section">
<h3 class="title is-4">1. Se connecter et naviguer</h3>
<?= guideCaptureFigure($captureSet, 'menu', 'Le menu, ouvert par le bouton ☰') ?>
<?= guideCaptureFigure($captureSet, 'login', 'La page de connexion') ?>
<p>Ce système accompagne une <strong>soirée enquête</strong> : il tient la mécanique de la partie, en tour par tour,
pendant que les joueurs négocient entre eux. <strong>Le jeu diplomatique reste indispensable</strong> : le système
ne remplace ni les alliances, ni les trahisons, ni l'orga.</p>
<p>Chaque joueur dirige une <strong>faction</strong> qui envoie ses <strong>agents</strong> sur les
<strong><?= htmlspecialchars($zoneType) ?>s</strong> de la carte pour enquêter, attaquer, revendiquer et découvrir des lieux secrets.
Un tour de jeu s'appelle ici un <strong><?= htmlspecialchars($timeValue) ?></strong>.</p>
<p><strong>Vos identifiants sont donnés par l'orga. Pour toute question, allez voir l'orga !</strong></p>
<p>Le bouton <strong>☰</strong>, en haut à gauche de chaque page, ouvre le menu :</p>
<ul>
  <li><strong>Accueil</strong> : l'introduction de la partie, puis votre faction, les <?= htmlspecialchars($zoneType) ?>s et vos agents sur une seule page.</li>
  <li><strong>Ma Faction</strong> : votre base, vos lieux, les informations à donner ou reçues.</li>
  <li><strong>Agents de la faction</strong> : recruter et diriger vos agents.</li>
  <?php if ($ressourcesEnabled) { ?>
  <li><strong>Ressources</strong> : ce que possède votre faction, ce qu'elle gagnera, et les dons.</li>
  <?php } ?>
  <li><strong>Les Zones</strong> : la carte et ce que vous savez de chaque <?= htmlspecialchars($zoneType) ?>.</li>
  <li><strong>Le Système</strong> : ce guide.</li>
  <li><strong>Mon compte</strong> : vos factions et votre mot de passe.</li>
</ul>
</div>

<div class="box guide-section">
<h3 class="title is-4">2. Un <?= htmlspecialchars($timeValue) ?> de jeu</h3>
<p>Pendant le <?= htmlspecialchars($timeValue) ?>, vous <strong>choisissez</strong> l'action de chaque agent. Rien n'est
résolu tout de suite : l'orga lance la <strong>fin de <?= htmlspecialchars($timeValue) ?></strong>, et le système résout
alors toutes les actions, dans cet ordre :</p>
<ol>
  <?php if ($ressourcesEnabled) { ?><li>Les ressources du <?= htmlspecialchars($timeValue) ?> sont versées.</li><?php } ?>
  <li>Les valeurs de chaque agent sont calculées et notées dans son rapport.</li>
  <li>Les attaques entre agents.</li>
  <li>Les attaques de lieux.</li>
  <li>Les revendications de <?= htmlspecialchars($zoneType) ?>s.</li>
  <?php if ($ressourcesEnabled) { ?><li>Les ressources qui dépendent des <?= htmlspecialchars($zoneType) ?>s tenu.es.</li><?php } ?>
  <li>Les enquêtes sur les agents.</li>
  <li>La recherche des lieux.</li>
  <li>Le compteur de recrutement repart à zéro et un nouveau <?= htmlspecialchars($timeValue) ?> commence.</li>
</ol>
<p>Les rapports de vos agents racontent ensuite ce qu'ils ont vécu. <strong>Conseil : prenez des notes sur papier.</strong></p>
</div>

<div class="workers guide-band"><div class="box guide-section">
<h3 class="title is-4">3. Vos agents</h3>
<?= guideCaptureFigure($captureSet, 'agents', 'La liste des agents') ?>
<h4 class="title is-5">Recruter</h4>
<?= guideCaptureFigure($captureSet, 'recrutement', 'Le choix entre plusieurs candidats') ?>
<ul>
  <li><strong>Recruter un serviteur</strong> propose plusieurs candidats et demande d'avoir une base. Vous en choisissez un seul.</li>
  <li><strong>Prendre le premier venu</strong> propose un candidat unique, à prendre ou à laisser.</li>
  <li>Le nom et les capacités de chaque candidat sont tirés au sort. Vous choisissez où l'envoyer, et une première maîtrise à lui enseigner.</li>
  <li><strong>Attention : un recrutement ouvert compte pour le <?= htmlspecialchars($timeValue) ?>. Ne quittez pas la page sans recruter, sinon l'agent est perdu !</strong></li>
</ul>

<h4 class="title is-5">L'action prévue en fin de <?= htmlspecialchars($timeValue) ?> (et dans quel <?= htmlspecialchars($zoneType) ?>)</h4>
<?= guideCaptureFigure($captureSet, 'agent', "Les actions d'un agent") ?>
<p>Chaque agent fait <strong>une seule action</strong> par <?= htmlspecialchars($timeValue) ?>, dans son <?= htmlspecialchars($zoneType) ?>.
Les formules donnent la valeur de l'agent pour cette action : « enquête », « attaque » et « défense » sont ses capacités.</p>
<p><strong>Actions de surveillance :</strong></p>
<ul>
  <li><strong><?= htmlspecialchars($labelInvestigate) ?> : <?= $actionRoll('Investigate', 'investigate') ?> + enquête.</strong> L'agent cherche activement les agents et les lieux secrets.</li>
  <li><strong><?= htmlspecialchars($labelPassive) ?> : <?= $actionRoll('Investigate', 'passive') ?> + enquête.</strong> L'agent reste à l'affût, sans prendre de risque, mais repère tout de même les agents et les lieux.</li>
  <?php if ($agentLocationAttack) { ?>
  <li><strong>Défendre le lieu : défense +<?= $defendLocationBonus ?>.</strong> L'agent défend l'un de vos lieux, et surveille en même temps. Les duels entre attaquants et défenseurs sont résolus en fin de <?= htmlspecialchars($timeValue) ?>.</li>
  <?php } ?>
</ul>
<p><strong>Actions passives :</strong></p>
<ul>
  <li><strong><?= htmlspecialchars($labelHide) ?> : pas d'enquête, discrétion +<?= $hideDiscretion ?>, défense +<?= $hideDefence ?>.</strong> L'agent se fait oublier et ne cherche rien.</li>
  <?php if ($claimEnabled) { ?>
  <li><strong>Revendiquer : vous contrôlerez ce <?= htmlspecialchars($zoneType) ?>, mais au nom de qui ?</strong>
    L'agent place le <?= htmlspecialchars($zoneType) ?> sous la bannière de la faction de votre choix, ou <strong>protège</strong> un <?= htmlspecialchars($zoneType) ?> déjà tenu. <strong>Revendiquer révèle l'agent.</strong></li>
  <?php } ?>
</ul>
<p><strong>Actions violentes :</strong></p>
<ul>
  <li><strong>Attaquer : <?= $actionRoll('Attack', 'attack') ?> + attaque contre <?= $actionRoll('Defence', 'passive') ?> + défense : quel(s) agent(s) ?</strong>
    L'agent attaque les agents ennemis repérés récemment dans son <?= htmlspecialchars($zoneType) ?>. L'attaque peut éliminer la cible, la capturer, échouer, ou se retourner contre l'attaquant.</li>
  <?php if ($agentLocationAttack) { ?>
  <li><strong>Attaquer le lieu : <?= $actionRoll('Attack', 'attack_location') ?> + attaque.</strong> L'agent attaque un lieu connu de son <?= htmlspecialchars($zoneType) ?>.</li>
  <?php } ?>
</ul>
<?php if (!empty($continuingActions)) { ?>
<p>Ces actions <strong>continuent</strong> d'un <?= htmlspecialchars($timeValue) ?> à l'autre : <?= htmlspecialchars(implode(', ', $continuingActions)) ?>.
Les autres retombent en « <?= htmlspecialchars(lcfirst($labelPassive)) ?> » une fois résolues.</p>
<?php } ?>

<h4 class="title is-5">Actions immédiates et évolutions</h4>
<ul>
  <li><strong>Déménager</strong> : l'agent change de <?= htmlspecialchars($zoneType) ?> tout de suite, et son action repasse à « <?= htmlspecialchars(lcfirst($labelPassive)) ?> ».</li>
  <li><strong>Donner</strong> : l'agent passe définitivement au service d'une autre faction.</li>
  <li><strong>Évolutions</strong> : avec l'ancienneté, un agent apprend de nouvelles maîtrises. Certains équipements ne sont disponibles que dans un <?= htmlspecialchars($zoneType) ?> que vous tenez.</li>
  <li>Un agent capturé devient votre <strong>prisonnier</strong> : vous pouvez le relâcher ou le transférer à une autre faction.</li>
</ul>

<h4 class="title is-5">Lire les rapports</h4>
<?= guideCaptureFigure($captureSet, 'rapport', "Le rapport d'un agent") ?>
<p>Le rapport de chaque agent est classé par <?= htmlspecialchars($timeValue) ?> :</p>
<ul>
  <li><strong>Changements</strong> : sa vie, ses déménagements et ses valeurs du <?= htmlspecialchars($timeValue) ?>.</li>
  <li><strong>Investigations</strong> : les agents qu'il a repérés. Plus il surpasse leur discrétion, plus il en apprend :
    leur nom et leur action, puis leurs capacités, puis le numéro de leur réseau, enfin le nom de la faction qui les dirige.</li>
  <li><strong>Recherches</strong> : qui tient le <?= htmlspecialchars($zoneType) ?>, et les lieux découverts. D'un lieu, on apprend d'abord le nom, puis la description, enfin ses secrets.</li>
</ul>
</div>
</div>

<div class="factions guide-band"><div class="box guide-section">
<h3 class="title is-4">4. Votre faction</h3>
<?= guideCaptureFigure($captureSet, 'faction', 'La page de votre faction') ?>
<ul>
  <li><strong>Votre base</strong> : la construire ou la déménager. Elle permet de recruter, et ses secrets ne se révèlent qu'à un enquêteur assez fort.</li>
  <li>Le coût de chaque opération est affiché à côté de son bouton.</li>
</ul>
<?= guideCaptureFigure($captureSet, 'lieux', $ownedArtefactsTitle . ' et vos lieux, floutés') ?>
<ul>
  <li><strong><?= htmlspecialchars($ownedArtefactsTitle) ?></strong> : les personnes ou les objets d'intérêt politique sous votre garde,
    et le lieu où ils se trouvent. Pour en remettre un.e à une autre faction, demandez à l'orga. Une attaque réussie sur un lieu
    rapporte ce qu'il abrite.</li>
  <li><strong>Vos lieux découverts</strong>, classés par <?= htmlspecialchars($zoneType) ?> : les lieux que vos agents ont trouvés, et ce qu'ils en ont appris.</li>
  <li><strong>Vos lieux secrets</strong>, classés par <?= htmlspecialchars($zoneType) ?> : la description de votre base et de son contenu, et les autres lieux que vous possédez.</li>
  <li><?php if ($agentLocationAttack) { ?>Pour attaquer ou défendre un lieu, donnez l'ordre à vos agents.<?php } else { ?>Un lieu découvert et destructible peut être attaqué depuis cette page, si vous avez une base.<?php } ?>
    Un lieu détruit peut être réparé.</li>
</ul>
<?= guideCaptureFigure($captureSet, 'informations', 'Donner des informations à une autre faction') ?>
<p><strong>Donner des informations à une autre faction :</strong></p>
<ul>
  <li><strong>Sur un agent repéré</strong> : son nom, pour que cette faction puisse l'attaquer. Il lui faut un agent dans le même <?= htmlspecialchars($zoneType) ?>.</li>
  <li><strong>Sur un lieu découvert</strong> : ce que vous en savez.</li>
</ul>
<?php if ($ressourcesEnabled) { ?>
<?= guideCaptureFigure($captureSet, 'ressources', 'Les ressources de la faction') ?>
<p>La page <strong>Ressources</strong> montre ce que possède votre faction, les règles qui lui en font gagner, et son
estimation pour le <?= htmlspecialchars($timeValue) ?> suivant. Vous pouvez y faire un don à une autre faction.</p>
<?php } ?>
</div>
</div>

<div class="zones guide-band"><div class="box guide-section">
<h3 class="title is-4">5. Les <?= htmlspecialchars($zoneType) ?>s</h3>
<?= guideCaptureFigure($captureSet, 'zones', 'La liste des ' . $zoneType . 's') ?>
<ul>
  <li>La <strong>carte</strong> se déplie en haut de la page.</li>
  <li>Chaque <?= htmlspecialchars($zoneType) ?> montre la <strong>bannière</strong> qui y est levée, et l'étiquette <strong>Sous notre contrôle</strong> quand il est à vous.</li>
  <li>Cliquer sur un <?= htmlspecialchars($zoneType) ?> déplie sa description, vos lieux, ceux que vous avez découverts et les agents présents.</li>
  <li>Échanger un <?= htmlspecialchars($zoneType) ?> avec une autre faction se négocie… puis se demande à l'orga.</li>
</ul>
</div>
</div>

<div class="box guide-section">
<h3 class="title is-4">6. Pour les curieux : les valeurs</h3>
<details>
<summary>Comment sont calculées les valeurs d'un agent</summary>
<p>Chaque agent a trois valeurs, recalculées à chaque fin de <?= htmlspecialchars($timeValue) ?> : <strong>enquête</strong>,
<strong>attaque</strong> et <strong>défense</strong>. Chacune additionne :</p>
<ul>
  <li>les bonus de ses capacités ;</li>
  <li>pour l'action active du <?= htmlspecialchars($timeValue) ?>, un dé de <?= $minRoll ?> à <?= $maxRoll ?> ; sinon une valeur fixe de <?= $passiveVal ?> ;</li>
  <li>un bonus quand votre faction tient le <?= htmlspecialchars($zoneType) ?> ;</li>
  <li>un bonus propre à certaines actions (se cacher, défendre un lieu).</li>
</ul>
<p>Une enquête compare l'enquête du chercheur à celle de l'agent observé. Une attaque compare l'attaque de l'attaquant à la défense de sa cible.
Les valeurs du <?= htmlspecialchars($timeValue) ?> sont écrites dans le rapport de l'agent.</p>
</details>
</div>

<div class="box guide-section">
<h3 class="title is-4">Pour les futurs organisateurs</h3>
<details>
<summary>Configuration, débogage et contact</summary>
<p>Presque tous les paramètres du système sont <strong>configurables</strong> dans la table <code>config</code> : valeurs, textes,
modes de calcul, seuils de découverte, résultats des attaques… Le détail de chaque clé est dans le
<a href="/<?= htmlspecialchars($folder) ?>/base/docConfig.php"><strong>guide de configuration</strong></a>.</p>
<p>Le mode <code>DEBUG_REPORT</code> affiche l'intégralité des calculs de chaque fin de <?= htmlspecialchars($timeValue) ?>, pour le suivi du développement ou l'équilibrage.</p>
<p>Les sources sont libres sur <a href="https://github.com/Edward-Croc/RPGConquestGame" target="_blank">GitHub</a>,
où vous pouvez déclarer les bugs dans les <a href="https://github.com/Edward-Croc/RPGConquestGame/issues" target="_blank">issues</a>.
Et en dernier recours, voici <details><summary>mon email :</summary><p>lenainblanc[at]wanadoo.fr</p></details></p>
</details>
</div>
</div>
